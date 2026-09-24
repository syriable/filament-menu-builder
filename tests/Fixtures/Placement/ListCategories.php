<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Placement;

use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;
}
