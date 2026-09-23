<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\UnknownItemType;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\HeadingType;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\LinkType;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Category;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;

it('ships with heading and link types', function (): void {
    expect(Menu::itemTypes())->toHaveKeys(['heading', 'link'])
        ->and(Menu::itemType('heading'))->toBeInstanceOf(HeadingType::class)
        ->and(Menu::itemType('heading')->hasUrl())->toBeFalse()
        ->and(Menu::itemType('link'))->toBeInstanceOf(LinkType::class)
        ->and(Menu::itemType('link')->hasUrl())->toBeTrue();
});

it('registers custom item types', function (): void {
    Menu::registerItemType(
        MenuItemType::make('page')
            ->label('Page')
            ->icon('heroicon-o-document')
            ->schema([TextInput::make('slug')->required()])
            ->rules(['slug' => ['required', 'string']])
            ->resolveUrlUsing(fn (array $data): string => '/pages/'.$data['slug']),
    );

    $type = Menu::itemType('page');
    $item = new MenuNode(key: 'k', type: 'page', label: 'Terms', data: ['slug' => 'terms']);

    expect($type->getLabel())->toBe('Page')
        ->and($type->getIcon())->toBe('heroicon-o-document')
        ->and($type->getFormSchema())->toHaveCount(1)
        ->and($type->getRules())->toBe(['slug' => ['required', 'string']])
        ->and($type->resolveUrl($item))->toBe('/pages/terms')
        ->and($type->resolveLabel($item))->toBe('Terms');
});

it('throws for unknown item types', function (): void {
    Menu::itemType('product');
})->throws(UnknownItemType::class);

it('integrates model backed item types', function (): void {
    $type = MenuItemType::make('category')
        ->model(Category::class, titleAttribute: 'name')
        ->resolveUrlUsing(fn (Category $record): string => '/categories/'.$record->slug);

    $category = Category::query()->create(['name' => 'Shoes', 'slug' => 'shoes']);
    $item = new MenuNode(key: 'k', type: 'category', data: ['record_id' => $category->id]);

    expect($type->isModelBacked())->toBeTrue()
        ->and($type->resolvesLabel())->toBeTrue()
        ->and($type->getRules())->toHaveKey('record_id')
        ->and($type->getFormSchema())->toHaveCount(1)
        ->and($type->getRecordKey($item))->toBe($category->id)
        ->and($type->findRecords([$category->id, $category->id]))->toHaveCount(1)
        ->and($type->resolveLabel($item, $category))->toBe('Shoes')
        ->and($type->resolveLabel($item->with(['label' => 'Custom']), $category))->toBe('Custom')
        ->and($type->resolveUrl($item, $category))->toBe('/categories/shoes');
});

it('rejects models that are not eloquent models', function (): void {
    MenuItemType::make('invalid')->model(stdClass::class);
})->throws(InvalidArgumentException::class);

it('falls back to the type label when no label can be resolved', function (): void {
    $type = MenuItemType::make('custom-type');

    expect($type->resolveLabel(new MenuNode(key: 'k', type: 'custom-type')))->toBe('Custom Type');
});

it('applies a query scope to model backed types', function (): void {
    $type = MenuItemType::make('category')->model(
        Category::class,
        'name',
        fn ($query) => $query->where('is_public', true),
    );

    $public = Category::query()->create(['name' => 'Public', 'slug' => 'public']);
    $private = Category::query()->create(['name' => 'Private', 'slug' => 'private', 'is_public' => false]);

    expect(array_keys($type->findRecords([$public->id, $private->id])))->toBe([$public->id]);
});
