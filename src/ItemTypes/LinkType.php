<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\ItemTypes;

use Closure;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Rules\ResolvableRoute;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver;

/**
 * A link to a URL or to a named Laravel route with parameters.
 */
class LinkType extends MenuItemType
{
    public const string KEY = 'link';

    #[Override]
    public static function make(string $key = self::KEY): static
    {
        return parent::make($key);
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->label ?? MenuRegistry::translate('types.link');
    }

    #[Override]
    protected function setUp(): void
    {
        $this
            ->icon('heroicon-o-link')
            ->schema(fn (): array => $this->linkSchema())
            ->rules([
                'link_type' => ['required', 'in:'.UrlResolver::TYPE_URL.','.UrlResolver::TYPE_ROUTE],
                'url' => ['exclude_unless:link_type,'.UrlResolver::TYPE_URL, 'required', 'string', 'max:2048'],
                'route' => ['exclude_unless:link_type,'.UrlResolver::TYPE_ROUTE, 'required', 'string', new ResolvableRoute],
                'route_parameters' => ['nullable', 'array'],
                'route_parameters.*' => ['nullable', $this->scalarRule()],
                'new_tab' => ['nullable', 'boolean'],
            ])
            ->resolveUrlUsing(static fn (array $data): ?string => app(UrlResolver::class)->resolve($data));
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected function linkSchema(): array
    {
        $isRoute = static fn (Get $get): bool => $get('link_type') === UrlResolver::TYPE_ROUTE;

        return [
            ToggleButtons::make('link_type')
                ->label(__('menu-builder::menu-builder.fields.link_type'))
                ->options([
                    UrlResolver::TYPE_URL => __('menu-builder::menu-builder.link_types.url'),
                    UrlResolver::TYPE_ROUTE => __('menu-builder::menu-builder.link_types.route'),
                ])
                ->default(UrlResolver::TYPE_URL)
                ->inline()
                ->grouped()
                ->required()
                ->live(),
            TextInput::make('url')
                ->label(__('menu-builder::menu-builder.fields.url'))
                ->placeholder('/about, https://example.com, #pricing, mailto:…')
                ->required()
                ->maxLength(2048)
                ->hidden($isRoute),
            Select::make('route')
                ->label(__('menu-builder::menu-builder.fields.route'))
                ->options(static fn (): array => app(UrlResolver::class)->selectableRoutes(
                    array_values(array_filter(config()->array('menu-builder.routes.exclude', []), is_string(...))),
                ))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(static function (?string $state, Set $set): void {
                    $names = app(UrlResolver::class)->routeParameterNames($state);

                    $set('route_parameters', array_fill_keys($names, ''));
                })
                ->visible($isRoute),
            KeyValue::make('route_parameters')
                ->label(__('menu-builder::menu-builder.fields.route_parameters'))
                ->keyLabel(__('menu-builder::menu-builder.fields.parameter'))
                ->valueLabel(__('menu-builder::menu-builder.fields.value'))
                ->visible($isRoute),
            Toggle::make('new_tab')
                ->label(__('menu-builder::menu-builder.fields.new_tab')),
        ];
    }

    protected function scalarRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_scalar($value)) {
                $fail(__('validation.string', ['attribute' => $attribute]));
            }
        };
    }
}
