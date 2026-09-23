{{-- A thin sidebar wrapper around <x-menu-builder::menu variant="tree" />. --}}
@props([
    'items' => [],
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => null,
    'withAssets' => null,
])

<x-menu-builder::menu
    :items="$items"
    variant="tree"
    :item-component="$itemComponent"
    :heading-tag="$headingTag"
    :label="$label"
    :direction="$direction"
    :with-assets="$withAssets"
    {{ $attributes->class(['mb-menu--sidebar']) }}
/>
