<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\ItemTypes;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver;

/**
 * A button rendered with Filament's <x-filament::button>.
 *
 * Without a URL it is a <button type="button"> whose behavior comes from its
 * HTML attributes (data-*, x-on:click, wire:click, ...). With a URL it is a
 * link styled as a button. Its color is the item's color.
 */
class ButtonType extends MenuItemType
{
    public const string KEY = 'button';

    #[Override]
    public static function make(string $key = self::KEY): static
    {
        return parent::make($key);
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->label ?? MenuRegistry::translate('types.button');
    }

    #[Override]
    protected function setUp(): void
    {
        $this
            ->icon('heroicon-o-cursor-arrow-rays')
            ->renderAs(RenderAs::Button)
            ->schema(fn (): array => $this->buttonSchema())
            ->rules([
                'size' => ['nullable', 'in:'.implode(',', array_column(Size::cases(), 'value'))],
                'outlined' => ['nullable', 'boolean'],
                'icon_position' => ['nullable', 'in:'.implode(',', array_column(IconPosition::cases(), 'value'))],
                'url' => ['nullable', 'string', 'max:2048'],
                'new_tab' => ['nullable', 'boolean'],
            ])
            ->resolveUrlUsing(static fn (array $data): ?string => app(UrlResolver::class)->url(
                is_string($data['url'] ?? null) ? $data['url'] : null,
            ));
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected function buttonSchema(): array
    {
        return [
            TextInput::make('url')
                ->label(__('menu-builder::menu-builder.fields.button_url'))
                ->helperText(__('menu-builder::menu-builder.fields.button_url_help'))
                ->maxLength(2048)
                ->live(onBlur: true)
                ->columnSpanFull(),
            Toggle::make('new_tab')
                ->label(__('menu-builder::menu-builder.fields.new_tab'))
                ->visible(static fn (Get $get): bool => filled($get('url')))
                ->columnSpanFull(),
            ToggleButtons::make('size')
                ->label(__('menu-builder::menu-builder.fields.button_size'))
                ->options([
                    Size::ExtraSmall->value => 'XS',
                    Size::Small->value => 'S',
                    Size::Medium->value => 'M',
                    Size::Large->value => 'L',
                    Size::ExtraLarge->value => 'XL',
                ])
                ->default(Size::Medium->value)
                ->inline()
                ->grouped(),
            ToggleButtons::make('icon_position')
                ->label(__('menu-builder::menu-builder.fields.icon_position'))
                ->options([
                    IconPosition::Before->value => __('menu-builder::menu-builder.icon_positions.before'),
                    IconPosition::After->value => __('menu-builder::menu-builder.icon_positions.after'),
                ])
                ->default(IconPosition::Before->value)
                ->inline()
                ->grouped(),
            Toggle::make('outlined')
                ->label(__('menu-builder::menu-builder.fields.outlined')),
        ];
    }
}
