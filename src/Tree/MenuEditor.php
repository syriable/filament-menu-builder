<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;

/**
 * Applies a single change to a tree and validates the result.
 *
 * Changes are made on a copy; the given tree is only modified when the
 * resulting tree passes the guard. Both the draft editor and the
 * programmatic actions use this class, so every change is validated the
 * same way no matter where it comes from.
 */
final readonly class MenuEditor
{
    public function __construct(private MenuTreeGuard $guard) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidMenuTree
     */
    public function create(MenuTree $tree, array $attributes, ?string $parentKey = null, ?int $position = null): MenuNode
    {
        $node = MenuNode::new($attributes);

        $this->apply($tree, static fn (MenuTree $copy) => $copy->add($node, $parentKey, $position), [$node->key]);

        return $node;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidMenuTree
     */
    public function update(MenuTree $tree, string $key, array $attributes): MenuNode
    {
        $node = $tree->get($key)->with($attributes);

        $this->apply($tree, static fn (MenuTree $copy) => $copy->replace($node), [$key]);

        return $node;
    }

    /**
     * @throws InvalidMenuTree
     */
    public function move(MenuTree $tree, string $key, ?string $parentKey, ?int $position = null): void
    {
        $this->apply($tree, static fn (MenuTree $copy) => $copy->move($key, $parentKey, $position));
    }

    /**
     * @throws InvalidMenuTree
     */
    public function moveRelativeTo(MenuTree $tree, string $key, string $targetKey, DropPosition $position): void
    {
        $this->apply($tree, static fn (MenuTree $copy) => $copy->moveRelativeTo($key, $targetKey, $position));
    }

    /**
     * @param  list<string>  $keys
     *
     * @throws InvalidMenuTree
     */
    public function reorder(MenuTree $tree, ?string $parentKey, array $keys): void
    {
        $this->apply($tree, static fn (MenuTree $copy) => $copy->reorder($parentKey, $keys));
    }

    /**
     * Deletes an item together with all of its descendants.
     *
     * @return list<string> The removed keys.
     */
    public function delete(MenuTree $tree, string $key): array
    {
        return $tree->remove($key);
    }

    /**
     * @param  callable(MenuTree): void  $change
     * @param  list<string>  $validateKeys  Items whose data changed and must be validated.
     */
    private function apply(MenuTree $tree, callable $change, array $validateKeys = []): void
    {
        $copy = clone $tree;

        $change($copy);

        $this->guard->assertValid($copy, $validateKeys);

        $change($tree);
    }
}
