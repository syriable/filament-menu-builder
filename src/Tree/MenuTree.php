<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

use Countable;
use Generator;
use Illuminate\Support\Collection;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;

/**
 * In-memory adjacency list of one placement.
 *
 * This is the single representation used by the editor draft, the
 * publisher and the frontend builder. Every structural operation keeps the
 * tree consistent (no dangling parents, no cycles); placement rules are
 * enforced separately by the MenuTreeGuard.
 */
final class MenuTree implements Countable
{
    private const string ROOT = '';

    /** @var array<string, MenuNode> */
    private array $nodes = [];

    /** @var array<string, string|null> */
    private array $parents = [];

    /** @var array<string, list<string>> Ordered child keys, indexed by parent key ('' = root). */
    private array $children = [self::ROOT => []];

    public function __construct(public readonly string $placement) {}

    /**
     * Builds a tree from database rows in O(n).
     *
     * @param  iterable<MenuItem>  $items
     */
    public static function fromModels(string $placement, iterable $items): self
    {
        $itemsByParent = [];

        foreach ($items as $item) {
            $itemsByParent[$item->parent_id ?? 0][] = $item;
        }

        foreach ($itemsByParent as &$siblings) {
            usort($siblings, static fn (MenuItem $a, MenuItem $b): int => [$a->sort_order, $a->id] <=> [$b->sort_order, $b->id]);
        }

        unset($siblings);

        $tree = new self($placement);
        $stack = [[0, null]];

        while ($stack !== []) {
            [$parentId, $parentKey] = array_pop($stack);

            foreach ($itemsByParent[$parentId] ?? [] as $item) {
                $node = MenuNode::fromModel($item);
                $tree->add($node, $parentKey);
                $stack[] = [$item->id, $node->key];
            }
        }

        return $tree;
    }

    /**
     * Builds a tree from nested arrays, e.g. in a seeder:
     *
     *     [['type' => 'heading', 'label' => 'Company', 'children' => [...]]]
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function fromNestedArray(string $placement, array $items): self
    {
        $tree = new self($placement);
        $tree->appendNested($items, null);

        return $tree;
    }

    /**
     * Restores a tree serialized with toArray(). Structural problems in the
     * payload are reported as an InvalidMenuTree exception.
     *
     * @param  array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>}  $array
     */
    public static function fromArray(array $array): self
    {
        $tree = new self($array['placement']);

        foreach ($array['items'] as $item) {
            $tree->add(MenuNode::fromArray($item['node']), $item['parent']);
        }

        return $tree;
    }

    /**
     * Serializes the tree in depth-first order.
     *
     * @return array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>}
     */
    public function toArray(): array
    {
        $items = [];

        foreach ($this->walk() as $key => $depth) {
            $items[] = ['node' => $this->nodes[$key]->toArray(), 'parent' => $this->parents[$key]];
        }

        return ['placement' => $this->placement, 'items' => $items];
    }

    /**
     * A hash of the complete structure and content, used for dirty checks
     * and to detect concurrent modifications.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', (string) json_encode($this->toArray()));
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    public function get(string $key): MenuNode
    {
        return $this->nodes[$key] ?? throw InvalidMenuTree::because("Menu item [{$key}] does not exist.", $key);
    }

    public function find(string $key): ?MenuNode
    {
        return $this->nodes[$key] ?? null;
    }

    public function findById(int $id): ?MenuNode
    {
        return $this->nodes[MenuNode::keyForId($id)] ?? null;
    }

    /**
     * @return array<string, MenuNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function parentOf(string $key): ?string
    {
        $this->get($key);

        return $this->parents[$key];
    }

    /**
     * @return list<string>
     */
    public function childrenOf(?string $key): array
    {
        return $this->children[$key ?? self::ROOT] ?? [];
    }

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->children[self::ROOT];
    }

    public function positionOf(string $key): int
    {
        return (int) array_search($key, $this->childrenOf($this->parentOf($key)), true);
    }

    /**
     * Depth of a node, starting at 1 for root items.
     */
    public function depthOf(string $key): int
    {
        $depth = 0;

        for ($current = $key; $current !== null; $current = $this->parents[$current] ?? null) {
            $depth++;
        }

        return $depth;
    }

    /**
     * Number of levels in the subtree of a node, 1 for a leaf.
     */
    public function heightOf(string $key): int
    {
        $height = 0;

        foreach ($this->walk($key) as $depth) {
            $height = max($height, $depth);
        }

        return $height;
    }

    /**
     * @return list<string>
     */
    public function descendantsOf(string $key): array
    {
        $this->get($key);

        $descendants = [];

        foreach ($this->walk($key) as $descendant => $depth) {
            if ($descendant !== $key) {
                $descendants[] = $descendant;
            }
        }

        return $descendants;
    }

    public function isDescendantOf(string $key, string $ancestor): bool
    {
        for ($current = $this->parents[$key] ?? null; $current !== null; $current = $this->parents[$current] ?? null) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Iterates depth-first in display order, yielding key => depth (1 based).
     * Pass a key to walk that node and its descendants only.
     *
     * @return Generator<string, int>
     */
    public function walk(?string $from = null): Generator
    {
        $stack = $from === null
            ? array_map(static fn (string $key): array => [$key, 1], array_reverse($this->roots()))
            : [[$from, 1]];

        while ($stack !== []) {
            [$key, $depth] = array_pop($stack);

            yield $key => $depth;

            foreach (array_reverse($this->childrenOf($key)) as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }
    }

    /**
     * @return Collection<string, MenuNode>
     */
    public function collect(): Collection
    {
        return collect($this->nodes);
    }

    public function add(MenuNode $node, ?string $parentKey = null, ?int $position = null): void
    {
        if ($this->has($node->key)) {
            throw InvalidMenuTree::because("Menu item [{$node->key}] already exists.", $node->key);
        }

        $this->insert($node->key, $parentKey, $position);
        $this->nodes[$node->key] = $node;
    }

    public function replace(MenuNode $node): void
    {
        $this->get($node->key);

        $this->nodes[$node->key] = $node;
    }

    /**
     * Moves a node (with its subtree) below a new parent. A null parent
     * moves it to the root; a null position appends it.
     */
    public function move(string $key, ?string $parentKey, ?int $position = null): void
    {
        $this->get($key);

        if ($parentKey === $key || ($parentKey !== null && $this->isDescendantOf($parentKey, $key))) {
            throw InvalidMenuTree::because(self::cycleMessage(), $key);
        }

        $this->detach($key);
        $this->insert($key, $parentKey, $position);
    }

    public function moveRelativeTo(string $key, string $targetKey, DropPosition $position): void
    {
        $this->get($targetKey);

        if ($position === DropPosition::Inside) {
            $this->move($key, $targetKey);

            return;
        }

        if ($key === $targetKey) {
            return;
        }

        $parentKey = $this->parents[$targetKey];

        if ($parentKey === $key || ($parentKey !== null && $this->isDescendantOf($parentKey, $key))) {
            throw InvalidMenuTree::because(self::cycleMessage(), $key);
        }

        $this->detach($key);

        $index = $this->positionOf($targetKey) + ($position === DropPosition::After ? 1 : 0);

        $this->insert($key, $parentKey, $index);
    }

    /**
     * Reorders the children of a parent. The given keys must be exactly the
     * current children of that parent.
     *
     * @param  list<string>  $keys
     */
    public function reorder(?string $parentKey, array $keys): void
    {
        $current = $this->childrenOf($parentKey);

        $sortedCurrent = $current;
        $sortedKeys = $keys;
        sort($sortedCurrent);
        sort($sortedKeys);

        if ($sortedCurrent !== $sortedKeys) {
            throw InvalidMenuTree::because('Reordering must contain exactly the current siblings.', $parentKey);
        }

        $this->children[$parentKey ?? self::ROOT] = $keys;
    }

    /**
     * Removes a node and all of its descendants.
     *
     * @return list<string> The removed keys.
     */
    public function remove(string $key): array
    {
        $removed = [$key, ...$this->descendantsOf($key)];

        $this->detach($key);

        foreach ($removed as $removedKey) {
            unset($this->nodes[$removedKey], $this->parents[$removedKey], $this->children[$removedKey]);
        }

        return $removed;
    }

    /**
     * @param  array<mixed>  $items
     */
    private function appendNested(array $items, ?string $parentKey): void
    {
        foreach ($items as $attributes) {
            if (! is_array($attributes)) {
                continue;
            }

            $children = $attributes['children'] ?? [];
            unset($attributes['children']);

            $node = MenuNode::new($attributes);
            $this->add($node, $parentKey);

            if (is_array($children) && $children !== []) {
                $this->appendNested($children, $node->key);
            }
        }
    }

    private static function cycleMessage(): string
    {
        $message = __('menu-builder::menu-builder.validation.cycle');

        return is_string($message) ? $message : 'An item cannot be moved below itself or one of its descendants.';
    }

    private function insert(string $key, ?string $parentKey, ?int $position): void
    {
        if ($parentKey !== null && ! $this->has($parentKey)) {
            throw InvalidMenuTree::because("Parent item [{$parentKey}] does not exist.", $key);
        }

        $siblings = $this->childrenOf($parentKey);
        $position = $position === null ? count($siblings) : max(0, min($position, count($siblings)));

        array_splice($siblings, $position, 0, [$key]);

        $this->children[$parentKey ?? self::ROOT] = $siblings;
        $this->parents[$key] = $parentKey;
    }

    private function detach(string $key): void
    {
        $parentKey = $this->parents[$key] ?? null;

        $this->children[$parentKey ?? self::ROOT] = array_values(array_filter(
            $this->childrenOf($parentKey),
            static fn (string $child): bool => $child !== $key,
        ));

        if ($parentKey !== null && $this->children[$parentKey] === []) {
            unset($this->children[$parentKey]);
        }
    }
}
