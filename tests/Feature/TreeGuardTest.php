<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\MenuVisibility;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTreeGuard;
use Syriable\Filament\Plugins\MenuBuilder\Tree\TreeViolation;

beforeEach(function (): void {
    registerTestPlacements();

    $this->guard = app(MenuTreeGuard::class);
    $this->messages = fn (MenuTree $tree): array => array_map(
        fn (TreeViolation $violation): string => $violation->message,
        $this->guard->validate($tree),
    );
});

it('accepts a valid tree', function (): void {
    $tree = MenuTree::fromNestedArray('footer', [
        headingItem('Company', ['children' => [linkItem('About'), linkItem('Jobs')]]),
    ]);

    expect($this->guard->validate($tree))->toBe([]);
});

it('rejects unknown placements', function (): void {
    expect(($this->messages)(new MenuTree('nowhere')))->toBe(['The menu placement "nowhere" is not registered.']);
});

it('rejects unknown item types', function (): void {
    $tree = MenuTree::fromNestedArray('header', [['type' => 'product', 'label' => 'Shoes']]);

    expect(($this->messages)($tree))->toBe(['"Shoes" has the unknown item type "product".']);
});

it('enforces root rules', function (): void {
    $tree = MenuTree::fromNestedArray('footer', [linkItem('About')]);

    expect(($this->messages)($tree))->toBe(['"Link" items cannot be placed at the top level of the Footer menu.']);
});

it('enforces child rules', function (): void {
    $tree = MenuTree::fromNestedArray('footer', [
        headingItem('Company', ['children' => [headingItem('Nested')]]),
    ]);

    expect(($this->messages)($tree))->toBe(['"Heading" items cannot be placed below "Heading" items.']);
});

it('enforces leaf rules', function (): void {
    Menu::registerPlacement(MenuPlacement::make('footer')->childItemTypes('link', []));

    $tree = MenuTree::fromNestedArray('footer', [
        linkItem('About', '/about', ['children' => [linkItem('Team')]]),
    ]);

    expect(($this->messages)($tree))->toBe(['"Link" items cannot contain other items.']);
});

it('enforces item types that cannot have children', function (): void {
    Menu::registerItemType(MenuItemType::make('divider')->withoutUrl()->canHaveChildren(false));

    $tree = MenuTree::fromNestedArray('header', [
        ['type' => 'divider', 'label' => '---', 'children' => [linkItem('Nope')]],
    ]);

    expect(($this->messages)($tree))->toBe(['"Divider" items cannot contain other items.']);
});

it('restricts item types per placement', function (): void {
    Menu::registerItemType(MenuItemType::make('group')->withoutUrl());
    Menu::registerPlacement(MenuPlacement::make('sidebar')->itemTypes(['group', 'link']));

    $tree = MenuTree::fromNestedArray('sidebar', [headingItem('Nope'), ['type' => 'group', 'label' => 'Yes']]);

    expect(($this->messages)($tree))->toBe(['"Heading" items cannot be used in the Sidebar menu.']);
});

it('enforces the maximum depth of each placement', function (int $levels, bool $valid): void {
    $items = [];

    for ($level = $levels; $level >= 1; $level--) {
        $items = [linkItem("Level {$level}", '/', $items === [] ? [] : ['children' => $items])];
    }

    expect($this->guard->validate(MenuTree::fromNestedArray('sidebar', $items)) === [])->toBe($valid);
})->with([
    [1, true],
    [3, true],
    [4, false],
]);

it('allows unlimited depth when no maximum is configured', function (): void {
    $items = [];

    for ($level = 12; $level >= 1; $level--) {
        $items = [linkItem("Level {$level}", '/', $items === [] ? [] : ['children' => $items])];
    }

    expect($this->guard->validate(MenuTree::fromNestedArray('header', $items)))->toBe([]);
});

it('validates labels and common attributes', function (): void {
    $tree = MenuTree::fromNestedArray('header', [
        linkItem('', '/', ['visibility' => 'admins', 'badge' => str_repeat('x', 51)]),
    ]);

    $fields = array_map(fn (TreeViolation $violation): ?string => $violation->field, $this->guard->validate($tree));

    expect($fields)->toBe(['label', 'badge', 'visibility']);
});

it('accepts custom visibility rules', function (): void {
    Menu::registerVisibility(MenuVisibility::make('admins', 'Admins', fn (): bool => false));

    $tree = MenuTree::fromNestedArray('header', [linkItem('Admin', '/', ['visibility' => 'admins'])]);

    expect($this->guard->validate($tree))->toBe([]);
});

it('validates link data', function (array $data, array $fields): void {
    $tree = MenuTree::fromNestedArray('header', [['type' => 'link', 'label' => 'Link', 'data' => $data]]);

    $violations = array_map(fn (TreeViolation $violation): ?string => $violation->field, $this->guard->validate($tree));

    expect($violations)->toBe($fields);
})->with([
    'valid url' => [['link_type' => 'url', 'url' => 'mailto:hello@example.com'], []],
    'valid route' => [['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => ['user' => 1]], []],
    'missing link type' => [['url' => '/'], ['data.link_type']],
    'missing url' => [['link_type' => 'url'], ['data.url']],
    'missing route' => [['link_type' => 'route'], ['data.route']],
    'unknown route' => [['link_type' => 'route', 'route' => 'nope'], ['data.route']],
    'missing route parameter' => [['link_type' => 'route', 'route' => 'users.show'], ['data.route']],
    'empty route parameter' => [['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => ['user' => '']], ['data.route']],
    'invalid parameters' => [['link_type' => 'route', 'route' => 'about', 'route_parameters' => ['a' => ['b']]], ['data.route_parameters.a']],
]);

it('validates custom item data with the rules of the type', function (): void {
    Menu::registerItemType(MenuItemType::make('page')->rules(['slug' => ['required', 'alpha_dash']]));

    $tree = MenuTree::fromNestedArray('header', [['type' => 'page', 'label' => 'Terms', 'data' => ['slug' => 'not valid']]]);

    expect($this->guard->validate($tree)[0]->field ?? null)->toBe('data.slug');
});

it('only validates the data of the given items', function (): void {
    $tree = MenuTree::fromNestedArray('header', [linkItem('', '/'), linkItem('Valid', '/')]);
    [$invalid, $valid] = $tree->roots();

    expect($this->guard->validate($tree, [$valid]))->toBe([])
        ->and($this->guard->validate($tree, [$invalid]))->toHaveCount(1);
});

it('throws with all violations', function (): void {
    $tree = MenuTree::fromNestedArray('footer', [linkItem(''), linkItem('Other')]);

    try {
        $this->guard->assertValid($tree);
    } catch (InvalidMenuTree $exception) {
        expect($exception->violations)->toHaveCount(3)
            ->and($exception->errorsFor($tree->roots()[0]))->toHaveKey('label');

        return;
    }

    $this->fail('The tree should be invalid.');
});

it('validates label translations', function (): void {
    $tree = MenuTree::fromNestedArray('header', [
        linkItem('Valid', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'label_translations' => ['ar' => 'صالح', 'pt_BR' => 'Válido', 'en' => null]]]),
        linkItem('Bad locale', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'label_translations' => ['not a locale' => 'x']]]),
        linkItem('Too long', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'label_translations' => ['ar' => str_repeat('x', 256)]]]),
        linkItem('Not text', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'label_translations' => ['ar' => ['x']]]]),
    ]);

    $fields = array_map(fn (TreeViolation $violation): string => $violation->field ?? '', $this->guard->validate($tree));

    expect(($this->messages)($tree))->toContain('"not a locale" is not a valid locale.')
        ->and($fields)->toBe(['data.label_translations', 'data.label_translations.ar', 'data.label_translations.ar']);
});

it('validates the text style', function (): void {
    $tree = MenuTree::fromNestedArray('header', [
        linkItem('Valid', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'text_style' => [
            'weight' => 'bold', 'size' => 'lg', 'italic' => true, 'underline' => 'none', 'transform' => 'uppercase', 'cursor' => 'pointer', 'hover_color' => '#f59e0b',
        ]]]),
        linkItem('Invalid', '/', ['data' => ['link_type' => 'url', 'url' => '/', 'text_style' => [
            'weight' => 'heavy', 'size' => '9xl', 'underline' => 'wavy', 'transform' => 'reverse', 'cursor' => 'grab', 'hover_color' => 'red;}',
        ]]]),
    ]);

    $fields = array_map(fn (TreeViolation $violation): string => $violation->field ?? '', $this->guard->validate($tree));

    expect($fields)->toEqualCanonicalizing([
        'data.text_style.weight',
        'data.text_style.size',
        'data.text_style.underline',
        'data.text_style.transform',
        'data.text_style.cursor',
        'data.text_style.hover_color',
    ]);
});
