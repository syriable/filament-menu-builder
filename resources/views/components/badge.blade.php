@props(['item'])

<x-filament::badge
    :color="$item->badgeColor ?? 'primary'"
    size="sm"
    {{ $attributes->class(['mb-badge', 'mb-badge--'.$item->badgePosition->value]) }}
>{{ $item->badge }}</x-filament::badge>
