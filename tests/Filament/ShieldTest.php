<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\MenuPlacements;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\MenuItemPolicy;

function menuPlugin(): MenuBuilderPlugin
{
    $plugin = Filament::getCurrentOrDefaultPanel()?->getPlugin('menu-builder');

    assert($plugin instanceof MenuBuilderPlugin);

    return $plugin;
}

/**
 * @param  array<class-string, array{pageFqcn: class-string, permissions: array<string, string>}>  $pages
 */
function fakeShieldPages(array $pages): void
{
    FilamentShield::swap(new class($pages)
    {
        /**
         * @param  array<class-string, array{pageFqcn: class-string, permissions: array<string, string>}>  $pages
         */
        public function __construct(private array $pages) {}

        /**
         * @return array<class-string, array{pageFqcn: class-string, permissions: array<string, string>}>
         */
        public function getPages(): array
        {
            return $this->pages;
        }
    });
}

beforeEach(function (): void {
    registerTestPlacements();

    fakeShieldPages([
        MenuPlacements::class => ['pageFqcn' => MenuPlacements::class, 'permissions' => ['View:MenuPlacements' => 'View Menu Placements']],
        ManageMenu::class => ['pageFqcn' => ManageMenu::class, 'permissions' => ['View:ManageMenu' => 'View Manage Menu']],
    ]);

    $this->actingAs(admin());
});

afterEach(fn () => menuPlugin()->shield(null));

it('is off unless the panel registers the Shield plugin', function (): void {
    expect(menuPlugin()->usesShield())->toBeFalse()
        ->and(MenuPlacements::getShieldPermission())->toBeNull();

    $this->get(MenuPlacements::getUrl())->assertOk();
});

it('turns on when the panel registers the Shield plugin', function (): void {
    $panel = Panel::make()->id('shielded')->plugin(FilamentShieldPlugin::make());

    expect(MenuBuilderPlugin::make()->usesShield($panel))->toBeTrue()
        ->and(MenuBuilderPlugin::make()->shield(false)->usesShield($panel))->toBeFalse();
});

it('reads the page permissions from Shield', function (): void {
    menuPlugin()->shield();

    expect(MenuPlacements::getShieldPermission())->toBe('View:MenuPlacements')
        ->and(ManageMenu::getShieldPermission())->toBe('View:ManageMenu');
});

it('denies the pages without their Shield permission', function (): void {
    menuPlugin()->shield();

    $this->get(MenuPlacements::getUrl())->assertForbidden();
    $this->get(ManageMenu::getUrl(['placement' => 'header']))->assertForbidden();
});

it('grants the pages with their Shield permission', function (): void {
    menuPlugin()->shield();
    Gate::define('View:MenuPlacements', fn (): bool => true);
    Gate::define('View:ManageMenu', fn (): bool => true);

    $this->get(MenuPlacements::getUrl())->assertOk();
    $this->get(ManageMenu::getUrl(['placement' => 'header']))->assertOk();
});

it('checks each page on its own', function (): void {
    menuPlugin()->shield();
    Gate::define('View:MenuPlacements', fn (): bool => true);

    $this->get(MenuPlacements::getUrl())->assertOk();
    $this->get(ManageMenu::getUrl(['placement' => 'header']))->assertForbidden();
});

it('hides the navigation item without the permission', function (): void {
    menuPlugin()->shield();

    expect(MenuPlacements::canAccess())->toBeFalse();

    Gate::define('View:MenuPlacements', fn (): bool => true);

    expect(MenuPlacements::canAccess())->toBeTrue();
});

it('allows pages that Shield excludes', function (): void {
    menuPlugin()->shield();
    fakeShieldPages([]);

    expect(MenuPlacements::getShieldPermission())->toBeNull();

    $this->get(MenuPlacements::getUrl())->assertOk();
});

it('still applies the placement policies', function (): void {
    menuPlugin()->shield();
    Gate::define('View:MenuPlacements', fn (): bool => true);
    Gate::policy(MenuItem::class, MenuItemPolicy::class);
    MenuItemPolicy::$denied = ['viewAny:header', 'viewAny:footer', 'viewAny:sidebar'];

    $this->get(MenuPlacements::getUrl())->assertForbidden();
});
