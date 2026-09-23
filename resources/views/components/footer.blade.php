{{-- A thin footer wrapper around <x-menu-builder::menu variant="columns" />. --}}
@props([
    'items' => [],
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => null,
    'itemClass' => null,
    'activeClass' => null,
    'dropdownClass' => null,
    'withAssets' => null,
])

<x-menu-builder::menu
    :items="$items"
    variant="columns"
    :item-component="$itemComponent"
    :heading-tag="$headingTag"
    :label="$label"
    :direction="$direction"
    :item-class="$itemClass"
    :active-class="$activeClass"
    :dropdown-class="$dropdownClass"
    :with-assets="$withAssets"
    {{ $attributes->class(['mb-menu--footer']) }}
/>
