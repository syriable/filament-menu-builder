<?php

declare(strict_types=1);

use Filament\Navigation\NavigationItem;

use function Pest\Livewire\livewire;

use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\MenuPlacements;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement\CategoryResource;

beforeEach(function (): void {
    registerTestPlacements();
    $this->actingAs(admin());
});

it('nests the menus below the resource in the navigation', function (): void {
    $item = collect(MenuPlacements::getNavigationItems())->first();

    expect($item)->toBeInstanceOf(NavigationItem::class)
        ->and($item->getParentItem())->toBe('Categories')
        ->and($item->getGroup())->toBe('Shop')
        ->and(MenuPlacements::getCluster())->toBeNull();

    $this->get(MenuPlacements::getUrl())->assertOk();
});

it('leads the breadcrumbs with the resource', function (): void {
    $placements = livewire(MenuPlacements::class)->instance()->getBreadcrumbs();
    $manage = livewire(ManageMenu::class, ['placement' => 'header'])->instance()->getBreadcrumbs();

    expect($placements)->toBe([CategoryResource::getUrl() => 'Categories', 0 => 'Menus'])
        ->and(array_values($manage))->toBe(['Categories', 'Menus', 'Header'])
        ->and(array_key_first($manage))->toBe(CategoryResource::getUrl());
});

it('prefers an explicit parent item and group', function (): void {
    filament()->getCurrentOrDefaultPanel()->getPlugin('menu-builder')->navigationParentItem('Catalog')->navigationGroup('Content');

    $item = collect(MenuPlacements::getNavigationItems())->first();

    expect($item->getParentItem())->toBe('Catalog')
        ->and($item->getGroup())->toBe('Content');
});

it('rejects classes that are not clusters or resources', function (string $method): void {
    MenuBuilderPlugin::make()->{$method}(stdClass::class);
})->with(['cluster', 'resource'])->throws(InvalidArgumentException::class);
