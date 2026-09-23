<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns\MutatesPublishedMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

final readonly class UpdateMenuItem
{
    use MutatesPublishedMenu;

    public function __construct(
        private MenuEditor $editor,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(MenuItem|int $item, array $attributes): MenuItem
    {
        $item = $this->resolveItem($item);
        $key = $this->keyOf($item);

        [, $result] = $this->mutate(
            $item->placement,
            fn (MenuTree $tree) => $this->editor->update($tree, $key, $attributes),
        );

        return $this->freshModel($result, $key);
    }
}
