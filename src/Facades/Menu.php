<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Facades;

use Illuminate\Support\Facades\Facade;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\MenuManager;

/**
 * @method static MenuManager registerPlacement(\Syriable\Filament\Plugins\MenuBuilder\MenuPlacement ...$placements)
 * @method static MenuManager registerItemType(\Syriable\Filament\Plugins\MenuBuilder\MenuItemType ...$types)
 * @method static MenuManager registerVisibility(\Syriable\Filament\Plugins\MenuBuilder\MenuVisibility ...$visibilities)
 * @method static array<string, \Syriable\Filament\Plugins\MenuBuilder\MenuPlacement> placements()
 * @method static \Syriable\Filament\Plugins\MenuBuilder\MenuPlacement placement(string $key)
 * @method static array<string, \Syriable\Filament\Plugins\MenuBuilder\MenuItemType> itemTypes()
 * @method static \Syriable\Filament\Plugins\MenuBuilder\MenuItemType itemType(string $key)
 * @method static \Illuminate\Support\Collection<int, \Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem> build(string $placement, ?\Illuminate\Contracts\Auth\Authenticatable $user = null, ?string $currentUrl = null)
 * @method static \Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree tree(string $placement)
 * @method static \Syriable\Filament\Plugins\MenuBuilder\Actions\PublishResult sync(string $placement, array<int, array<string, mixed>> $items)
 * @method static void flushCache(string $placement)
 * @method static \Syriable\Filament\Plugins\MenuBuilder\MenuRegistry registry()
 *
 * @see MenuManager
 */
final class Menu extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return MenuManager::class;
    }
}
