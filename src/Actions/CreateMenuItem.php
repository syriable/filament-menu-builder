<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns\MutatesPublishedMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Creates and immediately publishes a menu item, e.g. from a seeder.
 */
final readonly class CreateMenuItem
{
    use MutatesPublishedMenu;

    public function __construct(
        private MenuEditor $editor,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  type, label, data, icon, color, badge, badge_color, visibility, is_active
     */
    public function handle(string $placement, array $attributes, MenuItem|int|null $parent = null, ?int $position = null): MenuItem
    {
        $parentId = $parent instanceof MenuItem ? $parent->id : $parent;

        [$key, $result] = $this->mutate(
            $placement,
            fn (MenuTree $tree): string => $this->editor->create($tree, $attributes, $this->keyFor($parentId, $tree), $position)->key,
        );

        return $this->freshModel($result, $key);
    }
}
