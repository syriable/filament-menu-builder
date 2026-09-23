{{--
    The root element of one menu item: <a> for links, <button> for buttons and
    $headingTag (default <span>) for headings. The item's custom attributes are
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
