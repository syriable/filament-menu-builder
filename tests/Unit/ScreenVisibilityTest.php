<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Enums\Breakpoint;
use Syriable\Filament\Plugins\MenuBuilder\Support\ScreenVisibility;

it('shows on every screen by default', function (): void {
    $screens = ScreenVisibility::fromArray(null);

    expect($screens->isAlways())->toBeTrue()
        ->and($screens->classes())->toBe([])
        ->and($screens->toArray())->toBe([])
        ->and($screens->showsAt(320))->toBeTrue()
        ->and($screens->showsAt(2000))->toBeTrue();
});

it('turns breakpoints into classes', function (array $data, array $classes): void {
    expect(ScreenVisibility::fromArray($data)->classes())->toBe($classes);
})->with([
    'tablets and larger' => [['from' => 'md'], ['mb-show-from-md']],
    'phones only' => [['until' => 'md'], ['mb-hide-from-md']],
    'tablets only' => [['from' => 'md', 'until' => 'lg'], ['mb-show-from-md', 'mb-hide-from-lg']],
]);

it('knows on which widths it shows', function (array $data, int $width, bool $shown): void {
    expect(ScreenVisibility::fromArray($data)->showsAt($width))->toBe($shown);
})->with([
    [['from' => 'md'], 767, false],
    [['from' => 'md'], 768, true],
    [['until' => 'md'], 767, true],
    [['until' => 'md'], 768, false],
    [['from' => 'md', 'until' => 'lg'], 900, true],
    [['from' => 'md', 'until' => 'lg'], 1024, false],
]);

it('ignores unknown breakpoints and ranges that never match', function (array $data): void {
    expect(ScreenVisibility::fromArray($data)->isAlways())->toBeTrue();
})->with([
    [['from' => 'huge']],
    [['from' => 'lg', 'until' => 'md']],
    [['from' => 'md', 'until' => 'md']],
    [['from' => ['md']]],
]);

it('uses the Tailwind breakpoints', function (): void {
    expect(array_map(fn (Breakpoint $breakpoint): int => $breakpoint->pixels(), Breakpoint::cases()))
        ->toBe([640, 768, 1024, 1280, 1536]);
});
