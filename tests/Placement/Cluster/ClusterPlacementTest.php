<?php

declare(strict_types=1);

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

use function Pest\Livewire\livewire;

use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\MenuPlacements;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement\SettingsCluster;

beforeEach(function (): void {
    registerTestPlacements();
    $this->actingAs(admin());
});

it('places both menu pages in the cluster', function (): void {
    expect(MenuPlacements::getCluster())->toBe(SettingsCluster::class)
        ->and(ManageMenu::getCluster())->toBe(SettingsCluster::class)
        ->and(SettingsCluster::getClusteredComponents())->toContain(MenuPlacements::class, ManageMenu::class)
        // Only the menu pages: Page::$cluster is shared by every page.
        ->and(Filament\Pages\Dashboard::getCluster())->toBeNull();
});

it('serves the pages below the cluster URL', function (): void {
    expect(MenuPlacements::getUrl())->toEndWith('/admin/settings/menus')
        ->and(ManageMenu::getUrl(['placement' => 'header']))->toEndWith('/admin/settings/menus/header');

    $this->get(MenuPlacements::getUrl())->assertOk()->assertSee('Header');
    $this->get(ManageMenu::getUrl(['placement' => 'header']))->assertOk();
});

it('shows the menus in the cluster navigation only', function (): void {
    $labels = fn (array $navigation) => collect($navigation)
        ->flatMap(fn (NavigationGroup|NavigationItem $entry): iterable => $entry instanceof NavigationGroup ? $entry->getItems() : [$entry])
        ->map(fn (NavigationItem $item): string => $item->getLabel());

    $main = $labels(filament()->getNavigation());
    $cluster = $labels((new SettingsCluster)->getSubNavigation());

    expect($main)->toContain('Settings')->not->toContain('Menus')
        ->and($cluster)->toContain('Menus');
});

it('leads the breadcrumbs with the cluster', function (): void {
    $placements = livewire(MenuPlacements::class)->instance()->getBreadcrumbs();
    $manage = livewire(ManageMenu::class, ['placement' => 'header'])->instance()->getBreadcrumbs();

    expect(array_values($placements))->toBe(['Settings', 'Menus'])
        ->and(array_values($manage))->toBe(['Settings', 'Menus', 'Header']);
});
