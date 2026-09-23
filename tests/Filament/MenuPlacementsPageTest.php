<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\MenuPlacements;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\MenuItemPolicy;

beforeEach(function (): void {
    registerTestPlacements();

    $this->actingAs(admin());
});

it('lists every registered placement', function (): void {
    Menu::sync('header', [linkItem('Home', '/'), linkItem('About', '/about')]);

    livewire(MenuPlacements::class)
        ->assertOk()
        ->assertSeeInOrder(['Header', 'Footer', 'Sidebar'])
        ->assertSee('2 items')
        ->assertSee(ManageMenu::getUrl(['placement' => 'footer']));
});

it('shows placements registered by the application automatically', function (): void {
    Menu::registerPlacement(MenuPlacement::make('account-menu')->label('Account menu')->description('Shown in the user dropdown'));

    livewire(MenuPlacements::class)
        ->assertSee('Account menu')
        ->assertSee('Shown in the user dropdown');
});

it('is reachable through the panel navigation', function (): void {
    $this->get(MenuPlacements::getUrl())
        ->assertOk()
        ->assertSee('Menus');
});

it('hides placements the user may not view', function (): void {
    Gate::policy(MenuItem::class, MenuItemPolicy::class);
    MenuItemPolicy::$denied = ['viewAny:footer'];

    livewire(MenuPlacements::class)
        ->assertSee('Header')
        ->assertDontSee('Footer');
});

it('denies access when no placement may be viewed', function (): void {
    Gate::policy(MenuItem::class, MenuItemPolicy::class);
    MenuItemPolicy::$denied = ['viewAny:header', 'viewAny:footer', 'viewAny:sidebar'];

    $this->get(MenuPlacements::getUrl())->assertForbidden();
});
