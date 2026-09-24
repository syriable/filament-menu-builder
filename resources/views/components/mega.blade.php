{{-- A thin wrapper around <x-menu-builder::menu variant="mega" />. --}}
@props([
    'items' => [],
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => null,
    'itemClass' => null,
    'activeClass' => null,
    'panelClass' => null,
    'openDelay' => null,
    'closeDelay' => null,
    'panelBreakpoint' => null,
    'withAssets' => null,
])

<x-menu-builder::menu
    :items="$items"
    variant="mega"
    :item-component="$itemComponent"
    :heading-tag="$headingTag"
    :label="$label"
    :direction="$direction"
    :item-class="$itemClass"
    :active-class="$activeClass"
    :panel-class="$panelClass"
    :open-delay="$openDelay"
    :close-delay="$closeDelay"
    :panel-breakpoint="$panelBreakpoint"
    :with-assets="$withAssets"
    {{ $attributes }}
/>
