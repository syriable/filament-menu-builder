{{--
    One level of a menu. Items with children automatically become dropdowns
    (or nested lists); their children are rendered by this same component.

    The wrapper <li> only receives an item's custom attributes when the item
    targets its wrapper. The item's own element is rendered by $itemComponent.
--}}
@props([
    'items' => [],
    'variant' => 'dropdown',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'level' => 1,
])

<ul {{ $attributes->class(['mb-list']) }} data-level="{{ $level }}" role="list">
    @foreach ($items as $item)
        @php
            $hasChildren = $item->hasChildren();
            $submenuId = $hasChildren ? 'mb-submenu-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)) : null;
            $expanded = $hasChildren && $variant === 'tree' && $item->isActiveTrail;
        @endphp

        <li
            {{ $item->wrapperAttributes()->class([
                'mb-entry',
                'mb-has-children' => $hasChildren,
                'mb-current' => $item->isCurrent,
                'mb-active-trail' => $item->isActiveTrail,
            ]) }}
            @if ($expanded) data-open @endif
        >
            <div class="mb-row">
                <x-dynamic-component
                    :component="$itemComponent"
                    :item="$item"
                    :level="$level"
                    :heading-tag="$headingTag"
                />

                @if ($hasChildren && $variant !== 'columns')
                    <button
                        type="button"
                        class="mb-toggle"
                        data-mb-toggle
                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                        aria-controls="{{ $submenuId }}"
                        aria-label="{{ __('menu-builder::menu-builder.frontend.toggle', ['item' => $item->label]) }}"
                    >
                        <svg class="mb-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif
            </div>

            @if ($hasChildren)
                <x-menu-builder::items
                    :items="$item->children"
                    :variant="$variant"
                    :item-component="$itemComponent"
                    :heading-tag="$headingTag"
                    :level="$level + 1"
                    :id="$submenuId"
                    class="mb-submenu"
                />
            @endif
        </li>
    @endforeach
</ul>
