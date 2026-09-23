{{--
    The element of one menu item, built from Filament components:
    links use <x-filament::link>, buttons <x-filament::button> and headings a
    semibold <x-filament::link> rendered as $headingTag (default <span>).

    With `trigger`, the item opens a dropdown: it becomes a <button> (its page,
    if any, is listed as the first dropdown entry) and gets a chevron.

    The item's custom HTML attributes are applied here (unless they target the
    wrapper). Publish the package views to customize this markup.
--}}
@props([
    'item',
    'level' => 1,
    'headingTag' => 'span',
    'trigger' => false,
])

@php
    use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
    use Syriable\Filament\Plugins\MenuBuilder\Support\Icons;

    $icon = Icons::safe($item->icon);
    $topBadge = $item->hasBadge() && $item->badgePosition === BadgePosition::Top ? $item->badge : null;
    $color = $item->color ?? ($item->isActive() ? 'primary' : 'gray');
    $headingTag = preg_match('/^[a-z][a-z0-9-]*$/', $headingTag) ? $headingTag : 'span';

    $kind = match (true) {
        $item->isButton() => 'button',
        $item->isLink() => 'link',
        default => 'heading',
    };

    $elementAttributes = $item->itemAttributes()
        ->class(['mb-item', 'mb-item-'.$kind, 'mb-trigger' => $trigger])
        ->merge(array_filter([
            'aria-current' => ! $trigger && $item->isCurrent && $item->isLink() ? 'page' : null,
            'rel' => ! $trigger && $item->openInNewTab && $item->url !== null ? 'noopener noreferrer' : null,
        ]));

    $label = view('menu-builder::frontend.label', ['item' => $item, 'trigger' => $trigger]);
@endphp

@if ($item->isButton())
    <x-filament::button
        :tag="! $trigger && $item->url !== null ? 'a' : 'button'"
        :href="$trigger ? null : $item->url"
        :target="! $trigger && $item->openInNewTab ? '_blank' : null"
        :color="$item->color ?? 'primary'"
        :size="$item->buttonOption('size', 'md')"
        :outlined="(bool) $item->buttonOption('outlined', false)"
        :icon="$icon"
        :icon-position="$item->buttonOption('icon_position', 'before')"
        :badge="$topBadge"
        :badge-color="$item->badgeColor ?? 'primary'"
        :attributes="$elementAttributes"
    >{{ $label }}</x-filament::button>
@elseif ($item->isLink() || $trigger)
    <x-filament::link
        :tag="! $trigger && $item->isLink() ? 'a' : 'button'"
        :href="! $trigger && $item->isLink() ? $item->url : null"
        :target="! $trigger && $item->openInNewTab ? '_blank' : null"
        :color="$color"
        :icon="$icon"
        :weight="$item->isHeading() ? 'semibold' : null"
        :badge="$topBadge"
        :badge-color="$item->badgeColor ?? 'primary'"
        :attributes="$elementAttributes"
    >{{ $label }}</x-filament::link>
@else
    <x-filament::link
        :tag="$headingTag"
        :color="$item->color ?? 'gray'"
        :icon="$icon"
        weight="semibold"
        :badge="$topBadge"
        :badge-color="$item->badgeColor ?? 'primary'"
        :attributes="$elementAttributes"
    >{{ $label }}</x-filament::link>
@endif
