<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement;

use Filament\Panel;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\TestPanelProvider;

/**
 * The test panel with the menu pages placed in a cluster or under a
 * resource. Both are fixed when the panel registers, so each placement
 * has its own provider.
 */
abstract class PlacementPanelProvider extends TestPanelProvider
{
    abstract protected function configure(MenuBuilderPlugin $plugin): MenuBuilderPlugin;

    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->plugin($this->configure(MenuBuilderPlugin::make()))
            ->pages([SettingsCluster::class])
            ->resources([CategoryResource::class]);
    }
}
