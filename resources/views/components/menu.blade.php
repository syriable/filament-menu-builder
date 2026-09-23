{{--
    Renders a menu tree built by Menu::build().

    variant: "dropdown" (horizontal with dropdowns on wide screens, accordion on
             small screens), "tree" (vertical accordion) or "columns" (root items
             as columns with their children listed below, e.g. a footer).
--}}
@props([
    'items' => [],
    'variant' => 'dropdown',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'withAssets' => null,
])

@php
    $variant = in_array($variant, ['dropdown', 'tree', 'columns'], true) ? $variant : 'dropdown';
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
        :level="1"
        class="mb-root"
    />
</nav>
