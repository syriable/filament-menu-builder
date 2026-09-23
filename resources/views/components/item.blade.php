{{--
    The root element of one menu item: <a> for links, Filament's
    <x-filament::button> for buttons and $headingTag (default <span>) for headings. The item's custom attributes are
    applied here (unless it targets its wrapper) and can override the defaults.

    Publish the package views to customize this markup.
--}}
@props([
    'item',
    'level' => 1,
    'headingTag' => 'span',
])

@php
    use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;

    [$tag, $defaults] = match (true) {
        $item->isLink() => ['a', array_filter([
            'href' => $item->url,
            'aria-current' => $item->isCurrent ? 'page' : null,
            'target' => $item->openInNewTab ? '_blank' : null,
            'rel' => $item->openInNewTab ? 'noopener noreferrer' : null,
        ])],
        $item->isButton() => ['button', ['type' => 'button']],
        default => [preg_match('/^[a-z][a-z0-9-]*$/', $headingTag) ? $headingTag : 'span', []],
    };

    $kind = match (true) {
        $item->isLink() => 'link',
        $item->isButton() => 'button',
        default => 'heading',
    };

    $icon = filled($item->icon)
        ? rescue(fn () => svg($item->icon, 'mb-icon-svg')->toHtml(), null, report: false)
        : null;
@endphp

@if ($item->isButton())
    {{-- Buttons use Filament's button component: color, size, outline and icon come from the item. --}}
    <x-filament::button
        :tag="$item->url !== null ? 'a' : 'button'"
        :href="$item->url"
        :target="$item->openInNewTab ? '_blank' : null"
        :color="$item->color ?? 'primary'"
        :size="$item->buttonOption('size', 'md')"
        :outlined="(bool) $item->buttonOption('outlined', false)"
        :icon="$icon !== null ? $item->icon : null"
        :icon-position="$item->buttonOption('icon_position', 'before')"
        :badge="$item->hasBadge() && $item->badgePosition === BadgePosition::Top ? $item->badge : null"
        :badge-color="$item->badgeColor ?? 'primary'"
        :attributes="$item->itemAttributes()->class(['mb-item', 'mb-item-button'])"
    >
        @if ($item->hasBadge() && $item->badgePosition === BadgePosition::Start)
            <x-menu-builder::badge :item="$item" />
        @endif

        {{ $item->label }}

        @if ($item->hasBadge() && $item->badgePosition === BadgePosition::End)
            <x-menu-builder::badge :item="$item" />
        @endif
    </x-filament::button>
@else
<{{ $tag }} {{ $item->itemAttributes()->class(['mb-item', 'mb-item-'.$kind])->merge($defaults) }}>
    @if ($icon)
        <span class="mb-icon" aria-hidden="true">{!! $icon !!}</span>
    @endif

    @if ($item->hasBadge() && $item->badgePosition === BadgePosition::Start)
        <x-menu-builder::badge :item="$item" />
    @endif

    <span class="mb-label">{{ $item->label }}@if ($item->hasBadge() && $item->badgePosition === BadgePosition::Top)<x-menu-builder::badge :item="$item" />@endif</span>

    @if ($item->hasBadge() && $item->badgePosition === BadgePosition::End)
        <x-menu-builder::badge :item="$item" />
    @endif
</{{ $tag }}>
@endif
