<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement;

use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;

class ResourcePanelProvider extends PlacementPanelProvider
{
    protected function configure(MenuBuilderPlugin $plugin): MenuBuilderPlugin
    {
        return $plugin->resource(CategoryResource::class);
    }
}
