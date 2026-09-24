<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\ItemTypes;

use Filament\Forms\Components\Select;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\Enums\MegaColumns;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;

/**
 * A top level category of the mega menu: a link (URL or named route) whose
 * children are shown in a panel of one to four columns.
 *
 * The column count is stored in `data.columns`. When it is empty, the panel
 * gets one column per group, up to four.
 */
class MegaCategoryType extends LinkType
{
    public const string KEY = 'mega-category';

    #[Override]
    public static function make(string $key = self::KEY): static
    {
        return parent::make($key);
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->label ?? MenuRegistry::translate('types.mega_category');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon('heroicon-o-view-columns')
            ->rules([
                ...$this->rules,
                'columns' => ['nullable', 'integer', 'between:'.MegaColumns::One->value.','.MegaColumns::Four->value],
            ]);
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    #[Override]
    protected function linkSchema(): array
    {
        return [
            ...parent::linkSchema(),
            Select::make('columns')
                ->label(__('menu-builder::menu-builder.fields.columns'))
                ->helperText(__('menu-builder::menu-builder.fields.columns_help'))
                ->placeholder(__('menu-builder::menu-builder.fields.columns_auto'))
                ->options(MegaColumns::options()),
        ];
    }
}
