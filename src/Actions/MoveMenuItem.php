<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns\MutatesPublishedMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Moves an item (with its descendants) below another parent, or to the
 * root level when the parent is null.
 */
final readonly class MoveMenuItem
{
    use MutatesPublishedMenu;

    public function __construct(
        private MenuEditor $editor,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    public function handle(MenuItem|int $item, MenuItem|int|null $parent, ?int $position = null): MenuItem
    {
        $item = $this->resolveItem($item);
        $key = $this->keyOf($item);
        $parentId = $parent instanceof MenuItem ? $parent->id : $parent;

        [, $result] = $this->mutate(
            $item->placement,
            fn (MenuTree $tree) => $this->editor->move($tree, $key, $this->keyFor($parentId, $tree), $position),
        );

        return $this->freshModel($result, $key);
    }
}
