<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\Concerns;

use Filament\Facades\Filament;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuAuthorizer;

trait InteractsWithMenuPlugin
{
    protected static function plugin(): ?MenuBuilderPlugin
    {
        $plugin = Filament::getCurrentOrDefaultPanel()?->getPlugins()['menu-builder'] ?? null;

        return $plugin instanceof MenuBuilderPlugin ? $plugin : null;
    }

    protected static function registry(): MenuRegistry
    {
        return app(MenuRegistry::class);
    }

    protected static function authorizer(): MenuAuthorizer
    {
        return app(MenuAuthorizer::class);
    }

    /**
     * Whether the current user may view at least one placement.
     */
    protected static function canViewAnyPlacement(): bool
    {
        foreach (array_keys(static::registry()->placements()) as $placement) {
            if (static::authorizer()->can(MenuAuthorizer::VIEW_ANY, $placement)) {
                return true;
            }
        }

        return false;
    }
}
