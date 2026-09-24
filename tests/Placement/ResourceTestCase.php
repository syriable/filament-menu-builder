<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Placement;

use Override;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement\ResourcePanelProvider;
use Syriable\Filament\Plugins\MenuBuilder\Tests\TestCase;

abstract class ResourceTestCase extends TestCase
{
    #[Override]
    protected function panelProvider(): string
    {
        return ResourcePanelProvider::class;
    }
}
