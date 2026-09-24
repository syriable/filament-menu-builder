<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Placement;

use Override;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement\ClusterPanelProvider;
use Syriable\Filament\Plugins\MenuBuilder\Tests\TestCase;

abstract class ClusterTestCase extends TestCase
{
    #[Override]
    protected function panelProvider(): string
    {
        return ClusterPanelProvider::class;
    }
}
