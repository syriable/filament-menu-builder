{{-- A thin header wrapper around <x-menu-builder::menu variant="dropdown" />. --}}
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
    variant="dropdown"
    :item-component="$itemComponent"
    :heading-tag="$headingTag"
    :label="$label"
    :direction="$direction"
    :item-class="$itemClass"
    :active-class="$activeClass"
    :dropdown-class="$dropdownClass"
    :with-assets="$withAssets"
    {{ $attributes->class(['mb-menu--header']) }}
/>
