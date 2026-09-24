<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\User;
use Syriable\Filament\Plugins\MenuBuilder\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\MenuItemPolicy::$denied = [])
    ->in('Unit', 'Feature', 'Filament');

uses(Syriable\Filament\Plugins\MenuBuilder\Tests\Placement\ClusterTestCase::class)->in('Placement/Cluster');
uses(Syriable\Filament\Plugins\MenuBuilder\Tests\Placement\ResourceTestCase::class)->in('Placement/Resource');

/**
 * Registers the placements used throughout the test suite.
 */
function registerTestPlacements(): void
{
    Menu::registerPlacement(
        MenuPlacement::make('header')->label('Header')->icon('heroicon-o-bars-3'),
        MenuPlacement::make('footer')
            ->label('Footer')
            ->maxDepth(2)
            ->rootItemTypes(['heading'])
            ->childItemTypes('heading', ['link'])
            ->childItemTypes('link', []),
        MenuPlacement::make('sidebar')->label('Sidebar')->maxDepth(3),
    );
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function linkItem(string $label, string $url = '/', array $attributes = []): array
{
    return ['type' => 'link', 'label' => $label, 'data' => ['link_type' => 'url', 'url' => $url], ...$attributes];
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function headingItem(string $label, array $attributes = []): array
{
    return ['type' => 'heading', 'label' => $label, ...$attributes];
}

function admin(): User
{
    return User::query()->create([
        'name' => 'Admin',
        'email' => 'admin'.random_int(1, 1_000_000).'@example.com',
        'password' => 'secret',
    ]);
}
