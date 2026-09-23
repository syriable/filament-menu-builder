{{--
    Renders a menu tree built by Menu::build() with Filament's Blade components.

    variant: "dropdown" (horizontal; children open in Filament dropdowns, nested
             at any depth), "tree" (vertical accordion, e.g. a sidebar or mobile
             drawer) or "columns" (root items as columns, e.g. a footer).
    direction: "ltr" or "rtl"; defaults to Filament's direction for the locale.
--}}
@props([
    'items' => [],
    'variant' => 'dropdown',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => null,
    'withAssets' => null,
])

@php
    $variant = in_array($variant, ['dropdown', 'tree', 'columns'], true) ? $variant : 'dropdown';
    $direction = in_array($direction, ['ltr', 'rtl'], true)
        ? $direction
        : (__('filament-panels::layout.direction') === 'rtl' ? 'rtl' : 'ltr');
@endphp

@if ($withAssets ?? config('menu-builder.frontend.assets', true))
    @include('menu-builder::frontend.assets')
@endif

<nav
    {{ $attributes->class(['mb-menu', 'mb-menu--'.$variant]) }}
    data-mb-menu="{{ $variant }}"
    @if (filled($label)) aria-label="{{ $label }}" @endif
>
    <x-menu-builder::items
        :items="$items"
        :variant="$variant"
        :item-component="$itemComponent"
        :heading-tag="$headingTag"
        :direction="$direction"
        :level="1"
        class="mb-root"
    />
</nav>
