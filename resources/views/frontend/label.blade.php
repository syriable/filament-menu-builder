{{--
    The inside of an item: an inline badge at the start or end (top badges and
    dropdown list badges are rendered by the Filament component itself), the
    label, and a chevron for dropdown triggers.
--}}
@php
    use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;

    $inline ??= false;
    $direction ??= 'ltr';
    $chevron = match (true) {
        ! $trigger => null,
        $item->depth === 1 => 'heroicon-m-chevron-down',
        $direction === 'rtl' => 'heroicon-m-chevron-left',
        default => 'heroicon-m-chevron-right',
    };
@endphp
<span class="mb-content">
@if ($item->hasBadge() && $item->badgePosition === BadgePosition::Start)
    <x-menu-builder::badge :item="$item" />
@endif
<span class="mb-label">{{ $item->label }}</span>
@if (! $inline && $item->hasBadge() && $item->badgePosition === BadgePosition::End)
    <x-menu-builder::badge :item="$item" />
@endif
@if ($chevron)
    <x-filament::icon :icon="$chevron" class="mb-chevron" />
@endif
</span>
