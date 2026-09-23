<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns\MutatesPublishedMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Deletes an item together with all of its descendants in one transaction.
 */
final readonly class DeleteMenuItem
{
    use MutatesPublishedMenu;

    public function __construct(
        private MenuEditor $editor,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    /**
     * @return int The number of deleted items, including descendants.
     */
    public function handle(MenuItem|int $item): int
    {
        $item = $this->resolveItem($item);
        $key = $this->keyOf($item);

        [$removed] = $this->mutate($item->placement, fn (MenuTree $tree): array => $this->editor->delete($tree, $key));

        return count($removed);
    }
}
