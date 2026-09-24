{{--
    The panel of one mega menu category. Its groups flow into CSS columns; the
    column count comes from MegaColumns and sets --mb-mega-cols through
    data-mb-mega-columns, so the panel width is known before it opens.

    "All of …" links the category page. It is only shown when the panel was
    opened by touch, where the first tap opens the panel instead of the link.
--}}
@props([
    'item',
    'id',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'panelClass' => null,
])

@php
    use Syriable\Filament\Plugins\MenuBuilder\Enums\MegaColumns;
@endphp

<div
    id="{{ $id }}"
    @class(['mb-panel', 'mb-mega-panel', $panelClass])
    data-mb-mega-panel
    data-mb-mega-columns="{{ MegaColumns::for($item)->value }}"
>
    @if ($item->isLink() && ! $item->usesForm())
        <div class="mb-mega-view-all">
            <x-filament::link
                tag="a"
                :href="$item->url"
                :target="$item->openInNewTab ? '_blank' : null"
                :rel="$item->openInNewTab ? 'noopener noreferrer' : null"
                color="gray"
                class="mb-item mb-item-link mb-parent-link"
            ><span class="mb-content"><span class="mb-label">{{ __('menu-builder::menu-builder.frontend.view_all', ['item' => $item->label]) }}</span></span></x-filament::link>
        </div>
    @endif

    @foreach ($item->children as $group)
        <x-menu-builder::mega.group
            :item="$group"
            :item-component="$itemComponent"
            :heading-tag="$headingTag"
        />
    @endforeach
</div>
