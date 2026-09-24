{{--
    The "mega" variant: a horizontally scrollable row of categories. Each
    category with children opens a panel with its groups laid out in columns.

    Layout contract (see mega.css): the <nav> is the containing block of every
    panel, and the scrolling strip must never be positioned. A panel therefore
    escapes the strip's overflow clipping and is placed against the edges of
    the <nav>, not of its category.

    Behavior (hover intent, scroll arrows, panel placement, keyboard and touch)
    comes from the menuBuilderMega Alpine component. The markup only carries
    data-mb-mega-* hooks, so custom item components need no Alpine code.
--}}
@props([
    'items' => [],
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
    'label' => null,
    'direction' => 'ltr',
    'panelClass' => null,
    'openDelay' => null,
    'closeDelay' => null,
    'panelBreakpoint' => null,
])

@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Str;

    $rtl = $direction === 'rtl';

    $config = [
        'openDelay' => max(0, (int) ($openDelay ?? config('menu-builder.mega.open_delay', 100))),
        'closeDelay' => max(0, (int) ($closeDelay ?? config('menu-builder.mega.close_delay', 150))),
        'breakpoint' => max(0, (int) ($panelBreakpoint ?? config('menu-builder.mega.breakpoint', 1160))),
    ];

    $idPrefix = 'mb-mega-'.Str::lower(Str::random(8));
@endphp

<nav
    {{ $attributes->class(['mb-menu', 'mb-menu--mega']) }}
    data-mb-menu="mega"
    dir="{{ $direction }}"
    @if (filled($label)) aria-label="{{ $label }}" @endif
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('menu-builder-mega', 'syriable/filament-menu-builder') }}"
    x-data="menuBuilderMega(@js($config))"
>
    {{-- Pointer helpers only: keyboard focus scrolls a category into view by itself. --}}
    <button type="button" class="mb-mega-arrow mb-mega-arrow--start" data-mb-mega-scroll="start" tabindex="-1" aria-hidden="true" hidden>
        <x-filament::icon :icon="$rtl ? 'heroicon-m-chevron-right' : 'heroicon-m-chevron-left'" class="mb-mega-arrow-icon" />
    </button>

    <div class="mb-mega-strip" data-mb-mega-strip>
        <ul class="mb-list mb-root mb-mega-list" data-level="1" role="list">
            @foreach ($items as $item)
                @php
                    $panelId = $item->hasChildren() ? $idPrefix.'-'.$item->id : null;
                @endphp

                <li
                    {{ $item->wrapperAttributes()->class([
                        'mb-entry',
                        'mb-mega-entry',
                        'mb-has-children' => $panelId !== null,
                        'mb-current' => $item->isCurrent,
                        'mb-active-trail' => $item->isActiveTrail,
                    ]) }}
                    data-mb-mega-entry="{{ $item->id }}"
                    @if ($panelId) data-mb-mega-panel-id="{{ $panelId }}" @endif
                >
                    <div class="mb-row">
                        <x-dynamic-component
                            :component="$itemComponent"
                            :item="$item"
                            :level="1"
                            :heading-tag="$headingTag"
                        />
                    </div>

                    @if ($panelId)
                        <x-menu-builder::mega.panel
                            :item="$item"
                            :id="$panelId"
                            :item-component="$itemComponent"
                            :heading-tag="$headingTag"
                            :panel-class="$panelClass"
                        />
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <button type="button" class="mb-mega-arrow mb-mega-arrow--end" data-mb-mega-scroll="end" tabindex="-1" aria-hidden="true" hidden>
        <x-filament::icon :icon="$rtl ? 'heroicon-m-chevron-left' : 'heroicon-m-chevron-right'" class="mb-mega-arrow-icon" />
    </button>
</nav>
