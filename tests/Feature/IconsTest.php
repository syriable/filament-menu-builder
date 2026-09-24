<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Filament\Plugins\MenuBuilder\Support\Icons;

it('renders Icon Hub identifiers', function (): void {
    $icon = Icons::safe('heroicons:o-home');

    expect($icon)->toBeInstanceOf(Htmlable::class)
        ->and($icon->toHtml())->toContain('<svg');
});

it('keeps Blade Icons names', function (): void {
    expect(Icons::safe('heroicon-o-home'))->toBe('heroicon-o-home');
});

it('drops unknown icons in both formats', function (?string $icon): void {
    expect(Icons::safe($icon))->toBeNull();
})->with([null, '', 'heroicon-o-does-not-exist', 'heroicons:o-does-not-exist', 'nowhere:home']);

it('converts Blade Icons names to Icon Hub identifiers', function (?string $icon, ?string $expected): void {
    expect(Icons::toHubId($icon))->toBe($expected);
})->with([
    ['heroicon-o-home', 'heroicons:o-home'],
    ['heroicon-s-user', 'heroicons:s-user'],
    ['heroicons:o-home', 'heroicons:o-home'],
    ['custom-unknown-icon', 'custom-unknown-icon'],
    ['', null],
    [null, null],
]);
