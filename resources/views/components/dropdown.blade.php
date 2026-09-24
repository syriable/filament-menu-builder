{{--
    An item with children in the "dropdown" variant, as a Filament dropdown.

    The first level opens below its trigger; deeper levels open towards the
    inline end (right in LTR, left in RTL). Floating UI flips and shifts every
    panel so it stays inside the viewport. When the parent is a link, its own
    page is listed first so it stays reachable.
--}}
@props([
    'item',
    'level' => 1,
    'direction' => 'ltr',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
])

@aware(['itemClass' => null, 'activeClass' => null, 'dropdownClass' => null])

@php
    use Syriable\Filament\Plugins\MenuBuilder\Support\Icons;

    $placement = match (true) {
        $level === 1 => 'bottom-start',
        $direction === 'rtl' => 'left-start',
        default => 'right-start',
    };
@endphp

<x-filament::dropdown
    :placement="$placement"
    :offset="$level === 1 ? 8 : 4"
    shift
    class="mb-dropdown"
    data-level="{{ $level }}"
>
    <x-slot name="trigger">
        @if ($level === 1)
            <x-dynamic-component
                :component="$itemComponent"
                :item="$item"
                :level="$level"
                :heading-tag="$headingTag"
                :trigger="true"
            />
        @else
            <x-filament::dropdown.list.item
                tag="button"
                :icon="Icons::safe($item->icon)"
                :color="$item->color ?? 'gray'"
                :attributes="$item->itemAttributes()->class([
                    'mb-item',
                    'mb-trigger',
                    'mb-active' => $item->isActive(),
                    $itemClass,
                    $item->isActive() ? $activeClass : null,
                ])"
            >{{ view('menu-builder::frontend.label', ['item' => $item, 'trigger' => true, 'direction' => $direction]) }}</x-filament::dropdown.list.item>
        @endif
    </x-slot>

    <div @class(['mb-panel', $dropdownClass])>
        @if ($item->isLink() && ! $item->usesForm())
            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="$item->url"
                    :target="$item->openInNewTab ? '_blank' : null"
                    :aria-current="$item->isCurrent ? 'page' : null"
                    @class([
                        'mb-item',
                        'mb-parent-link',
                        'mb-active' => $item->isCurrent,
                        $itemClass,
                        $item->isCurrent ? $activeClass : null,
                    ])
                >{{ $item->label }}</x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        @endif

        <x-filament::dropdown.list>
            @foreach ($item->children as $child)
                <x-menu-builder::dropdown-item
                    :item="$child"
                    :level="$level + 1"
                    :direction="$direction"
                    :item-component="$itemComponent"
                    :heading-tag="$headingTag"
                />
            @endforeach
        </x-filament::dropdown.list>
    </div>
</x-filament::dropdown>
