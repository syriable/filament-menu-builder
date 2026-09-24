{{--
    Renders a menu tree built by Menu::build() with Filament's Blade components.

    variant: "dropdown" (horizontal; children open in Filament dropdowns, nested
             at any depth), "tree" (vertical accordion, e.g. a sidebar or mobile
             drawer), "columns" (root items as columns, e.g. a footer) or "mega"
             (a scrollable row of categories whose groups open in wide panels
             on hover; see MegaMenu::register()).
    direction: "ltr" or "rtl"; defaults to Filament's direction for the locale.
    item-class / active-class: classes for every item element / the active ones.
    dropdown-class: classes for the content of every dropdown panel.
    panel-class, open-delay, close-delay, panel-breakpoint: "mega" variant only.
--}}
@props([
    'items' => [],
    'variant' => 'dropdown',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => null,
    'itemClass' => null,
    'activeClass' => null,
    'dropdownClass' => null,
    'panelClass' => null,
    'openDelay' => null,
    'closeDelay' => null,
    'panelBreakpoint' => null,
    'withAssets' => null,
])

@php
    $variant = in_array($variant, ['dropdown', 'tree', 'columns', 'mega'], true) ? $variant : 'dropdown';
    $direction = in_array($direction, ['ltr', 'rtl'], true)
        ? $direction
        : (__('filament-panels::layout.direction') === 'rtl' ? 'rtl' : 'ltr');
    $withAssets = $withAssets ?? config('menu-builder.frontend.assets', true);
@endphp

@if ($withAssets)
    @include('menu-builder::frontend.assets')

    @if ($variant === 'mega')
        @include('menu-builder::frontend.mega-assets')
    @endif
@endif

@if ($variant === 'mega')
    <x-menu-builder::mega.menu
        :items="$items"
        :item-component="$itemComponent"
        :heading-tag="$headingTag"
        :label="$label"
        :direction="$direction"
        :panel-class="$panelClass"
        :open-delay="$openDelay"
        :close-delay="$closeDelay"
        :panel-breakpoint="$panelBreakpoint"
        {{ $attributes }}
    />
@else
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
@endif
