<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement;

use Filament\Clusters\Cluster;

class SettingsCluster extends Cluster
{
    protected static ?string $slug = 'settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $clusterBreadcrumb = 'Settings';
}
