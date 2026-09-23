{{--
    One level of a menu. What an item with children becomes is decided here:
    a Filament dropdown in the "dropdown" variant, an accordion (toggled with a
    Filament icon button) in the "tree" variant, and an always visible list in
    the "columns" variant.
--}}
@props([
    'items' => [],
    'variant' => 'dropdown',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'direction' => 'ltr',
    'level' => 1,
])

<ul {{ $attributes->class(['mb-list']) }} data-level="{{ $level }}" role="list">
    @foreach ($items as $item)
        @php
            $hasChildren = $item->hasChildren();
            $isDropdown = $hasChildren && $variant === 'dropdown';
            $isAccordion = $hasChildren && $variant === 'tree';
            $expanded = $isAccordion && $item->isActiveTrail;
            $submenuId = $isAccordion ? 'mb-submenu-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)) : null;
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
            @if ($isDropdown)
                <x-menu-builder::dropdown
                    :item="$item"
                    :level="$level"
                    :direction="$direction"
                    :item-component="$itemComponent"
                    :heading-tag="$headingTag"
                />
            @else
                <div class="mb-row">
                    <x-dynamic-component
                        :component="$itemComponent"
                        :item="$item"
                        :level="$level"
                        :heading-tag="$headingTag"
                    />

                    @if ($isAccordion)
                        <x-filament::icon-button
                            icon="heroicon-m-chevron-down"
                            color="gray"
                            size="sm"
                            :label="__('menu-builder::menu-builder.frontend.toggle', ['item' => $item->label])"
                            class="mb-toggle"
                            data-mb-toggle
                            :aria-expanded="$expanded ? 'true' : 'false'"
                            :aria-controls="$submenuId"
                        />
                    @endif
                </div>

                @if ($hasChildren)
                    <x-menu-builder::items
                        :items="$item->children"
                        :variant="$variant"
                        :item-component="$itemComponent"
                        :heading-tag="$headingTag"
                        :direction="$direction"
                        :level="$level + 1"
                        :id="$submenuId"
                        class="mb-submenu"
                    />
                @endif
            @endif
        </li>
    @endforeach
</ul>
