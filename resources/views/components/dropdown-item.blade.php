{{--
    One entry inside a Filament dropdown panel: a nested dropdown when the item
    has children, a dropdown header for headings, a Filament button for button
    items, otherwise a dropdown list item (a link, or a <button> without URL).
    A custom item component, when given, renders every leaf entry instead.

    Entries whose wrapper has attributes (screen visibility classes, or HTML
    attributes that target the wrapper) are wrapped in a <div> carrying them.
--}}
@props([
    'item',
    'level' => 2,
    'direction' => 'ltr',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
])

@aware(['itemClass' => null, 'activeClass' => null])

@php
    use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
    use Syriable\Filament\Plugins\MenuBuilder\Support\Icons;

    $icon = Icons::safe($item->icon);
    $badge = $item->hasBadge() && $item->badgePosition !== BadgePosition::Start ? $item->badge : null;
    $label = view('menu-builder::frontend.label', ['item' => $item, 'trigger' => false, 'inline' => true]);
    $wrapper = $item->wrapperAttributes();
    $wrapped = $wrapper->getAttributes() !== [];
@endphp

@if ($wrapped)
    <div {{ $wrapper->class(['mb-panel-entry']) }}>
@endif

@if ($item->hasChildren())
    <x-menu-builder::dropdown
        :item="$item"
        :level="$level"
        :direction="$direction"
        :item-component="$itemComponent"
        :heading-tag="$headingTag"
    />
@elseif ($item->isButton() || $itemComponent !== 'menu-builder::item')
    <div class="mb-dropdown-entry">
        <x-dynamic-component
            :component="$itemComponent"
            :item="$item"
            :level="$level"
            :heading-tag="$headingTag"
        />
    </div>
@elseif ($item->isHeading())
    <x-filament::dropdown.header
        :icon="$icon"
        :color="$item->color ?? 'gray'"
        :attributes="$item->itemAttributes()->class(['mb-item', 'mb-item-heading', $itemClass])"
    >{{ $label }}</x-filament::dropdown.header>
@else
    <x-filament::dropdown.list.item
        :tag="$item->url !== null ? 'a' : 'button'"
        :href="$item->url"
        :target="$item->openInNewTab ? '_blank' : null"
        :icon="$icon"
        :color="$item->color ?? 'gray'"
        :badge="$badge"
        :badge-color="$item->badgeColor ?? 'primary'"
        :attributes="$item->itemAttributes()
            ->class([
                'mb-item',
                'mb-item-'.($item->isButton() ? 'button' : 'link'),
                'mb-active' => $item->isCurrent,
                $itemClass,
                $item->isCurrent ? $activeClass : null,
            ])
            ->merge(array_filter([
                'aria-current' => $item->isCurrent ? 'page' : null,
                'rel' => $item->openInNewTab && $item->url !== null ? 'noopener noreferrer' : null,
            ]))"
    >{{ $label }}</x-filament::dropdown.list.item>
@endif

@if ($wrapped)
    </div>
@endif
