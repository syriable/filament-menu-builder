@props(['item'])

<span
    {{ $attributes->class(['mb-badge', 'mb-badge--'.$item->badgePosition->value]) }}
    @if (filled($item->badgeColor)) data-color="{{ $item->badgeColor }}" @endif
>{{ $item->badge }}</span>
