<?php

declare(strict_types=1);

use Filament\Schemas\Components\Component;
use Syriable\Filament\Plugins\MenuBuilder\Enums\MegaColumns;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\MegaCategoryType;
use Syriable\Filament\Plugins\MenuBuilder\MegaMenu;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;

/**
 * @param  list<array<string, mixed>>  $children
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function megaPlacementCategory(string $label, array $children = [], array $data = []): array
{
    return [
        'type' => MegaCategoryType::KEY,
        'label' => $label,
        'data' => ['link_type' => 'url', 'url' => '/'.strtolower($label), ...$data],
        'children' => $children,
    ];
}

/**
 * @param  list<array<string, mixed>>  $children
 * @return array<string, mixed>
 */
function megaPlacementLink(string $label, array $children = []): array
{
    return [
        'type' => 'link',
        'label' => $label,
        'data' => ['link_type' => 'url', 'url' => '/'.strtolower(str_replace(' ', '-', $label))],
        'children' => $children,
    ];
}

beforeEach(function (): void {
    MegaMenu::register();
});

describe('MegaMenu::register', function (): void {
    it('registers the mega category type and the preset placement', function (): void {
        $placement = Menu::placement(MegaMenu::PLACEMENT);

        expect(Menu::itemType(MegaCategoryType::KEY))->toBeInstanceOf(MegaCategoryType::class)
            ->and($placement->getMaxDepth())->toBe(3)
            ->and($placement->getRootItemTypes())->toBe([MegaCategoryType::KEY])
            ->and($placement->getChildItemTypes(MegaCategoryType::KEY))->toBe(['heading', 'link'])
            ->and($placement->getChildItemTypes('heading'))->toBe(['link'])
            ->and($placement->getChildItemTypes('link'))->toBe(['link']);
    });

    it('can register several placements without registering the type twice', function (): void {
        $type = Menu::itemType(MegaCategoryType::KEY);

        MegaMenu::register('services');

        expect(Menu::itemType(MegaCategoryType::KEY))->toBe($type)
            ->and(Menu::placement('services')->getRootItemTypes())->toBe([MegaCategoryType::KEY]);
    });

    it('lets the preset be configured', function (): void {
        MegaMenu::register('services', fn (MenuPlacement $placement): MenuPlacement => $placement->label('Services')->sort(5));
        MegaMenu::register('products', function (MenuPlacement $placement): void {
            $placement->label('Products');
        });

        expect(Menu::placement('services')->getLabel())->toBe('Services')
            ->and(Menu::placement('services')->getSort())->toBe(5)
            ->and(Menu::placement('products')->getLabel())->toBe('Products');
    });

    it('adds a columns select to the category form', function (): void {
        $names = array_map(
            static fn (Component $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
            Menu::itemType(MegaCategoryType::KEY)->getFormSchema(),
        );

        expect($names)->toContain('link_type', 'url', 'route', 'columns');
    });
});

describe('mega menu placement rules', function (): void {
    it('accepts categories with heading and link groups', function (): void {
        Menu::sync(MegaMenu::PLACEMENT, [
            megaPlacementCategory('Design', data: ['columns' => 3], children: [
                ['type' => 'heading', 'label' => 'Logo & Brand', 'children' => [megaPlacementLink('Logo Design')]],
                megaPlacementLink('Web Design', [megaPlacementLink('Landing Pages')]),
            ]),
        ]);

        $category = Menu::build(MegaMenu::PLACEMENT)->first();

        expect($category->type)->toBe(MegaCategoryType::KEY)
            ->and($category->url)->toEndWith('/design')
            ->and(MegaColumns::for($category))->toBe(MegaColumns::Three)
            ->and($category->children)->toHaveCount(2)
            ->and($category->children[0]->children[0]->label)->toBe('Logo Design');
    });

    it('rejects trees that the mega variant cannot render', function (array $items): void {
        expect(fn () => Menu::sync(MegaMenu::PLACEMENT, $items))->toThrow(InvalidMenuTree::class);
    })->with([
        'a link at the root' => [[megaPlacementLink('Design')]],
        'a category inside a category' => [[megaPlacementCategory('Design', [megaPlacementCategory('Nested')])]],
        'a button as a group' => [[megaPlacementCategory('Design', [['type' => 'button', 'label' => 'Buy']])]],
        'a heading inside a group' => [[megaPlacementCategory('Design', [megaPlacementLink('Web', [['type' => 'heading', 'label' => 'Deep']])])]],
        'a fourth level' => [[megaPlacementCategory('Design', [megaPlacementLink('Web', [megaPlacementLink('Pages', [megaPlacementLink('Deep')])])])]],
        'five columns' => [[megaPlacementCategory('Design', data: ['columns' => 5])]],
        'text as columns' => [[megaPlacementCategory('Design', data: ['columns' => 'wide'])]],
        'a category without a URL' => [[['type' => MegaCategoryType::KEY, 'label' => 'Design', 'data' => ['link_type' => 'url']]]],
    ]);
});
