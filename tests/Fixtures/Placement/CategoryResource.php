<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Category;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationLabel = 'Categories';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCategories::route('/')];
    }
}
