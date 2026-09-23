{{-- A thin footer wrapper around <x-menu-builder::menu variant="columns" />. --}}
@props([
    'items' => [],
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'withAssets' => null,
])

<x-menu-builder::menu
    :items="$items"
    variant="columns"
    :item-component="$itemComponent"
    :heading-tag="$headingTag"
    :label="$label"
    :with-assets="$withAssets"
    {{ $attributes->class(['mb-menu--footer']) }}
/>
