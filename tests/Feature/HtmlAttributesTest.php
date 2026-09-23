<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTreeGuard;
use Syriable\Filament\Plugins\MenuBuilder\Tree\TreeViolation;

beforeEach(function (): void {
    registerTestPlacements();
});

it('accepts arbitrary attribute names', function (string $name): void {
    expect(HtmlAttributes::isValidName($name))->toBeTrue();
})->with([
    'class', 'id', 'role', 'title', 'target', 'rel', 'data-modal', 'data-size', 'aria-label', 'aria-expanded',
    'x-data', 'x-on:click', 'x-on:click.prevent', '@click', ':class', 'x-bind:class', 'wire:click', 'wire:target',
    'wire:click.prevent', 'hx-get', 'data-ünïcode',
]);

it('rejects malformed attribute names', function (mixed $name): void {
    expect(HtmlAttributes::isValidName($name))->toBeFalse();
})->with([
    'empty' => [''],
    'space' => ['data modal'],
    'quote' => ['data"x'],
    'single quote' => ["data'x"],
    'equals' => ['a=b'],
    'tag' => ['onclick"><script>'],
    'slash' => ['a/b'],
    'less than' => ['a<b'],
    'newline' => ["a\nb"],
    'too long' => [str_repeat('a', 101)],
    'integer' => [42],
]);

it('normalizes values', function (): void {
    expect(HtmlAttributes::normalize([
        'class' => ' btn ',
        'data-flag' => '',
        'disabled' => '',
        'hidden' => 'false',
        'required' => 'true',
        'readonly' => '0',
        'aria-hidden' => 'false',
        'data-count' => 3,
        'data-null' => null,
        'data-array' => ['x'],
        'bad name' => 'x',
    ]))->toBe([
        'class' => 'btn',
        'data-flag' => true,
        'disabled' => true,
        'required' => true,
        'aria-hidden' => 'false',
        'data-count' => '3',
    ])->and(HtmlAttributes::normalize('not an array'))->toBe([]);
});

it('escapes values when building the attribute bag', function (): void {
    $bag = HtmlAttributes::bag(['title' => '"><script>', 'disabled' => true, 'x-data' => true]);

    expect((string) $bag)->toBe('title="&quot;&gt;&lt;script&gt;" disabled="disabled" x-data=""');
});

/**
 * @param  array<string, mixed>  $data
 * @return list<string|null>
 */
function renderingViolations(string $type, array $data): array
{
    $tree = MenuTree::fromNestedArray('header', [['type' => $type, 'label' => 'Item', 'data' => $data]]);

    return array_map(fn (TreeViolation $violation): ?string => $violation->field, app(MenuTreeGuard::class)->validate($tree));
}

it('validates rendering options and attributes on every write path', function (string $type, array $data, array $fields): void {
    expect(renderingViolations($type, $data))->toBe($fields);
})->with([
    'valid attributes' => ['heading', ['attributes' => ['class' => 'x', 'x-on:click' => 'open = true', 'disabled' => '']], []],
    'malformed attribute name' => ['heading', ['attributes' => ['bad name' => 'x']], ['data.attributes']],
    'array attribute value' => ['heading', ['attributes' => ['data-x' => ['nested']]], ['data.attributes']],
    'attributes not an array' => ['heading', ['attributes' => 'class=x'], ['data.attributes']],
    'button' => ['heading', ['render_as' => 'button'], []],
    'unknown render as' => ['heading', ['render_as' => 'dropdown'], ['data.render_as']],
    'heading cannot be a link' => ['heading', ['render_as' => 'link'], ['data.render_as']],
    'badge positions' => ['heading', ['badge_position' => 'top'], []],
    'unknown badge position' => ['heading', ['badge_position' => 'left'], ['data.badge_position']],
    'attribute target' => ['heading', ['attribute_target' => 'wrapper'], []],
    'unknown attribute target' => ['heading', ['attribute_target' => 'body'], ['data.attribute_target']],
    'link as button needs no url' => ['link', ['render_as' => 'button'], []],
    'link as heading needs no url' => ['link', ['render_as' => 'heading'], []],
    'link as link still needs a url' => ['link', ['render_as' => 'link', 'link_type' => 'url'], ['data.url']],
]);

it('keeps existing menus working without rendering options', function (): void {
    Menu::sync('header', [linkItem('Home', '/'), headingItem('Group')]);

    [$home, $group] = Menu::build('header')->all();

    expect($home->isLink())->toBeTrue()
        ->and($home->attributes)->toBe([])
        ->and($home->badgePosition->value)->toBe('end')
        ->and($home->attributeTarget->value)->toBe('item')
        ->and($group->isHeading())->toBeTrue()
        ->and($home->toArray())->toHaveKeys(['render_as', 'attributes', 'attribute_target', 'badge_position']);
});

it('resolves rendering options in the menu builder', function (): void {
    Menu::sync('header', [
        ['type' => 'link', 'label' => 'Login', 'data' => ['render_as' => 'button', 'attributes' => ['data-modal' => 'login']]],
        ['type' => 'link', 'label' => 'Section', 'data' => ['link_type' => 'url', 'url' => '/x', 'render_as' => 'heading', 'badge_position' => 'start', 'attribute_target' => 'wrapper']],
    ]);

    // Rows written around the validation are still rendered safely.
    $login = Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem::query()->where('label', 'Login')->firstOrFail();
    $login->update(['data' => [...$login->data, 'attributes' => ['data-modal' => 'login', 'bad"name' => 'x']]]);
    Menu::flushCache('header');

    [$login, $section] = Menu::build('header')->all();

    expect($login->isButton())->toBeTrue()
        ->and($login->url)->toBeNull()
        ->and($login->attributes)->toBe(['data-modal' => 'login'])
        ->and($section->isHeading())->toBeTrue()
        ->and($section->badgePosition->value)->toBe('start')
        ->and($section->attributeTarget->value)->toBe('wrapper');
});
