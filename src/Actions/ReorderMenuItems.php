<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns\MutatesPublishedMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Reorders the children of one parent (or the root items of a placement).
 */
final readonly class ReorderMenuItems
{
    use MutatesPublishedMenu;

    public function __construct(
        private MenuEditor $editor,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    /**
     * @param  list<int>  $orderedIds  Exactly the ids of the current siblings, in their new order.
     */
    public function handle(string $placement, MenuItem|int|null $parent, array $orderedIds): void
    {
        $parentId = $parent instanceof MenuItem ? $parent->id : $parent;

        $this->mutate($placement, fn (MenuTree $tree) => $this->editor->reorder(
            $tree,
            $this->keyFor($parentId, $tree),
            array_map(MenuNode::keyForId(...), $orderedIds),
        ));
    }
}
