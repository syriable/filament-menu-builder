<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tree\DropPosition;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

function node(string $key, string $type = 'link'): MenuNode
{
    return new MenuNode(key: $key, type: $type, label: strtoupper($key));
}

/**
 * @return array<string, list<string>>
 */
function shape(MenuTree $tree): array
{
    $shape = ['root' => $tree->roots()];

    foreach (array_keys($tree->nodes()) as $key) {
        if ($tree->childrenOf($key) !== []) {
            $shape[$key] = $tree->childrenOf($key);
        }
    }

    return $shape;
}

beforeEach(function (): void {
    $this->tree = new MenuTree('header');
    $this->tree->add(node('a'));
    $this->tree->add(node('b'));
    $this->tree->add(node('c'));
});

it('adds nodes at the root, below parents and at positions', function (): void {
    $this->tree->add(node('b1'), 'b');
    $this->tree->add(node('first'), null, 0);

    expect(shape($this->tree))->toBe([
        'root' => ['first', 'a', 'b', 'c'],
        'b' => ['b1'],
    ])->and($this->tree->count())->toBe(5);
});

it('rejects duplicate keys and missing parents', function (): void {
    expect(fn () => $this->tree->add(node('a')))->toThrow(InvalidMenuTree::class)
        ->and(fn () => $this->tree->add(node('x'), 'missing'))->toThrow(InvalidMenuTree::class);
});

it('reorders siblings', function (): void {
    $this->tree->reorder(null, ['c', 'a', 'b']);

    expect($this->tree->roots())->toBe(['c', 'a', 'b']);
});

it('rejects a reorder that does not contain exactly the siblings', function (): void {
    $this->tree->reorder(null, ['a', 'b']);
})->throws(InvalidMenuTree::class);

it('moves an item below another item and back to the root', function (): void {
    $this->tree->add(node('design'), 'b');
    $this->tree->add(node('development'), 'b');

    $this->tree->move('development', 'design');

    expect(shape($this->tree))->toBe([
        'root' => ['a', 'b', 'c'],
        'b' => ['design'],
        'design' => ['development'],
    ]);

    $this->tree->move('development', null, 0);

    expect(shape($this->tree))->toBe([
        'root' => ['development', 'a', 'b', 'c'],
        'b' => ['design'],
    ]);
});

it('moves items relative to a target', function (DropPosition $position, array $expected): void {
    $this->tree->moveRelativeTo('a', 'c', $position);

    expect(shape($this->tree))->toBe($expected);
})->with([
    'before' => [DropPosition::Before, ['root' => ['b', 'a', 'c']]],
    'after' => [DropPosition::After, ['root' => ['b', 'c', 'a']]],
    'inside' => [DropPosition::Inside, ['root' => ['b', 'c'], 'c' => ['a']]],
]);

it('moves a subtree together with its descendants', function (): void {
    $this->tree->add(node('a1'), 'a');
    $this->tree->add(node('a2'), 'a1');

    $this->tree->move('a', 'c');

    expect($this->tree->depthOf('a2'))->toBe(4)
        ->and($this->tree->descendantsOf('c'))->toBe(['a', 'a1', 'a2']);
});

it('prevents cycles', function (callable $move): void {
    $this->tree->add(node('a1'), 'a');
    $this->tree->add(node('a2'), 'a1');

    $move($this->tree);
})->with([
    'own parent' => [fn (MenuTree $tree) => $tree->move('a', 'a')],
    'below a child' => [fn (MenuTree $tree) => $tree->move('a', 'a1')],
    'below a grandchild' => [fn (MenuTree $tree) => $tree->move('a', 'a2')],
    'inside a descendant' => [fn (MenuTree $tree) => $tree->moveRelativeTo('a', 'a2', DropPosition::Inside)],
    'next to a descendant' => [fn (MenuTree $tree) => $tree->moveRelativeTo('a', 'a2', DropPosition::After)],
])->throws(InvalidMenuTree::class);

it('removes an item with its complete subtree', function (): void {
    $this->tree->add(node('b1'), 'b');
    $this->tree->add(node('b2'), 'b1');

    expect($this->tree->remove('b'))->toBe(['b', 'b1', 'b2'])
        ->and(shape($this->tree))->toBe(['root' => ['a', 'c']])
        ->and($this->tree->count())->toBe(2);
});

it('knows depth, height and descendants', function (): void {
    $this->tree->add(node('a1'), 'a');
    $this->tree->add(node('a2'), 'a1');

    expect($this->tree->depthOf('a'))->toBe(1)
        ->and($this->tree->depthOf('a2'))->toBe(3)
        ->and($this->tree->heightOf('a'))->toBe(3)
        ->and($this->tree->heightOf('b'))->toBe(1)
        ->and($this->tree->descendantsOf('a'))->toBe(['a1', 'a2'])
        ->and($this->tree->isDescendantOf('a2', 'a'))->toBeTrue()
        ->and($this->tree->isDescendantOf('a', 'a2'))->toBeFalse();
});

it('walks depth first in display order', function (): void {
    $this->tree->add(node('a1'), 'a');
    $this->tree->add(node('b1'), 'b');

    expect(iterator_to_array($this->tree->walk()))->toBe(['a' => 1, 'a1' => 2, 'b' => 1, 'b1' => 2, 'c' => 1]);
});

it('serializes and restores the tree', function (): void {
    $this->tree->add(node('a1'), 'a');

    $restored = MenuTree::fromArray($this->tree->toArray());

    expect($restored->toArray())->toBe($this->tree->toArray())
        ->and($restored->fingerprint())->toBe($this->tree->fingerprint());
});

it('changes its fingerprint when the structure or content changes', function (): void {
    $fingerprint = $this->tree->fingerprint();

    $moved = clone $this->tree;
    $moved->reorder(null, ['b', 'a', 'c']);

    $edited = clone $this->tree;
    $edited->replace($edited->get('a')->with(['label' => 'Changed']));

    expect($moved->fingerprint())->not->toBe($fingerprint)
        ->and($edited->fingerprint())->not->toBe($fingerprint)
        ->and($this->tree->fingerprint())->toBe($fingerprint);
});

it('builds a tree from nested arrays', function (): void {
    $tree = MenuTree::fromNestedArray('footer', [
        headingItem('Company', ['children' => [linkItem('About'), linkItem('Jobs')]]),
        headingItem('Legal'),
    ]);

    [$company, $legal] = $tree->roots();

    expect($tree->count())->toBe(4)
        ->and($tree->get($company)->label)->toBe('Company')
        ->and(array_map(fn (string $key): ?string => $tree->get($key)->label, $tree->childrenOf($company)))->toBe(['About', 'Jobs'])
        ->and($tree->childrenOf($legal))->toBe([]);
});

it('builds a tree from database rows in sort order', function (): void {
    $services = MenuItem::factory()->create(['label' => 'Services', 'sort_order' => 1]);
    $home = MenuItem::factory()->create(['label' => 'Home', 'sort_order' => 0]);
    MenuItem::factory()->create(['label' => 'Development', 'parent_id' => $services->id, 'sort_order' => 1]);
    MenuItem::factory()->create(['label' => 'Design', 'parent_id' => $services->id, 'sort_order' => 0]);
    MenuItem::factory()->create(['label' => 'Elsewhere', 'placement' => 'footer']);

    $tree = MenuTree::fromModels('header', MenuItem::query()->where('placement', 'header')->get());

    $labels = fn (array $keys): array => array_map(fn (string $key): ?string => $tree->get($key)->label, $keys);

    expect($labels($tree->roots()))->toBe(['Home', 'Services'])
        ->and($labels($tree->childrenOf(MenuNode::keyForId($services->id))))->toBe(['Design', 'Development'])
        ->and($tree->findById($home->id)?->id)->toBe($home->id);
});

it('builds large trees in linear time', function (): void {
    $items = [];
    $id = 0;

    // 20 000 items, 5 levels deep.
    $append = function (?int $parentId, int $depth) use (&$append, &$items, &$id): void {
        for ($i = 0; $i < 7; $i++) {
            $item = new MenuItem(['placement' => 'header', 'type' => 'link', 'label' => "Item {$id}", 'sort_order' => $i]);
            $item->id = ++$id;
            $item->parent_id = $parentId;
            $items[] = $item;

            if ($depth < 5) {
                $append($item->id, $depth + 1);
            }
        }
    };
    $append(null, 1);

    $small = array_slice($items, 0, 2_000);

    $time = function (array $rows): float {
        $start = hrtime(true);
        MenuTree::fromModels('header', $rows);

        return (hrtime(true) - $start) / count($rows);
    };

    $time($small); // warm up

    // Quadratic behaviour would make the per-item cost of the larger tree ~10x higher.
    expect($time($items))->toBeLessThan($time($small) * 4)
        ->and(MenuTree::fromModels('header', $items)->count())->toBe(count($items));
});
