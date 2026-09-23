<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Exceptions\UnknownPlacement;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;

it('registers placements at runtime', function (): void {
    Menu::registerPlacement(
        MenuPlacement::make('account-menu')->label('Account')->icon('heroicon-o-user'),
        MenuPlacement::make('dashboard-menu'),
    );

    expect(Menu::placements())->toHaveKeys(['account-menu', 'dashboard-menu'])
        ->and(Menu::placement('account-menu')->getLabel())->toBe('Account')
        ->and(Menu::placement('account-menu')->getIcon())->toBe('heroicon-o-user')
        ->and(Menu::placement('dashboard-menu')->getLabel())->toBe('Dashboard Menu');
});

it('registers placements from the config file', function (): void {
    $registry = new MenuRegistry([
        'primary' => ['label' => 'Primary navigation', 'max_depth' => 3, 'sort' => 2],
        'footer' => [
            'description' => 'Bottom of every page',
            'root_item_types' => ['heading'],
            'child_item_types' => ['heading' => ['link'], 'link' => []],
            'item_types' => ['heading', 'link'],
        ],
    ]);

    $footer = $registry->placement('footer');

    expect($registry->placement('primary')->getLabel())->toBe('Primary navigation')
        ->and($registry->placement('primary')->getMaxDepth())->toBe(3)
        ->and($footer->getDescription())->toBe('Bottom of every page')
        ->and($footer->getRootItemTypes())->toBe(['heading'])
        ->and($footer->getChildItemTypes('heading'))->toBe(['link'])
        ->and($footer->getChildItemTypes('link'))->toBe([])
        ->and($footer->getChildItemTypes('other'))->toBeNull()
        ->and($footer->getItemTypes())->toBe(['heading', 'link']);
});

it('lets programmatic registration override the config', function (): void {
    config()->set('menu-builder.placements', ['header' => ['label' => 'From config']]);
    app()->forgetInstance(MenuRegistry::class);

    Menu::registerPlacement(MenuPlacement::make('header')->label('From code'));

    expect(Menu::placement('header')->getLabel())->toBe('From code');
});

it('sorts placements by sort order, then registration order', function (): void {
    Menu::registerPlacement(
        MenuPlacement::make('footer')->sort(10),
        MenuPlacement::make('sidebar')->sort(5),
        MenuPlacement::make('navbar')->sort(5),
        MenuPlacement::make('header')->sort(1),
    );

    expect(array_keys(Menu::placements()))->toBe(['header', 'sidebar', 'navbar', 'footer']);
});

it('supports different max depths per placement', function (): void {
    Menu::registerPlacement(
        MenuPlacement::make('flat')->maxDepth(1),
        MenuPlacement::make('deep')->maxDepth(10),
        MenuPlacement::make('unlimited'),
    );

    expect(Menu::placement('flat')->getMaxDepth())->toBe(1)
        ->and(Menu::placement('deep')->getMaxDepth())->toBe(10)
        ->and(Menu::placement('unlimited')->getMaxDepth())->toBeNull();
});

it('throws for unknown placements', function (): void {
    Menu::placement('nowhere');
})->throws(UnknownPlacement::class);

it('resolves the allowed item types from placement rules', function (): void {
    registerTestPlacements();
    $registry = app(MenuRegistry::class);

    $allowed = fn (string $placement, ?string $parentType): array => array_keys($registry->allowedItemTypes($registry->placement($placement), $parentType));

    expect($allowed('header', null))->toBe(['heading', 'link', 'button'])
        ->and($allowed('header', 'link'))->toBe(['heading', 'link', 'button'])
        ->and($allowed('footer', null))->toBe(['heading'])
        ->and($allowed('footer', 'heading'))->toBe(['link'])
        ->and($allowed('footer', 'link'))->toBe([]);
});
