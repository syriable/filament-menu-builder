<?php

declare(strict_types=1);

use Filament\Support\Enums\FontWeight;
use Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle;

it('is empty by default', function (): void {
    $style = TextStyle::fromArray(null);

    expect($style->isEmpty())->toBeTrue()
        ->and($style->classes())->toBe([])
        ->and($style->styles())->toBe([]);
});

it('turns the options into classes and a hover color property', function (): void {
    $style = TextStyle::fromArray([
        'weight' => 'bold',
        'size' => 'lg',
        'italic' => true,
        'underline' => 'none',
        'transform' => 'uppercase',
        'cursor' => 'pointer',
        'hover_color' => '#f59e0b',
    ]);

    expect($style->weight)->toBe(FontWeight::Bold)
        ->and($style->classes())->toBe([
            'mb-weight-bold',
            'mb-text-lg',
            'mb-italic',
            'mb-underline-none',
            'mb-transform-uppercase',
            'mb-cursor-pointer',
            'mb-hover-color',
        ])
        ->and($style->styles())->toBe(['--mb-item-hover-color: #f59e0b'])
        ->and($style->toArray())->toBe([
            'weight' => 'bold',
            'size' => 'lg',
            'italic' => true,
            'underline' => 'none',
            'transform' => 'uppercase',
            'cursor' => 'pointer',
            'hover_color' => '#f59e0b',
        ]);
});

it('ignores unknown values', function (): void {
    $style = TextStyle::fromArray([
        'weight' => 'heavy',
        'size' => '9xl',
        'italic' => 'no',
        'underline' => 'wavy',
        'transform' => 'reverse',
        'cursor' => 'grab',
        'hover_color' => 'red; background: url(x)',
    ]);

    expect($style->isEmpty())->toBeTrue();
});

it('accepts common color notations only', function (string $color, bool $valid): void {
    expect(TextStyle::isColor($color))->toBe($valid);
})->with([
    ['#fff', true],
    ['#f59e0bcc', true],
    ['rgb(245 158 11)', true],
    ['rgba(245, 158, 11, 0.5)', true],
    ['hsl(38 92% 50%)', true],
    ['oklch(0.77 0.16 70)', true],
    ['red', false],
    ['#ggg', false],
    ['rgb(1,2,3); color: red', false],
    ['"><script>', false],
    ['var(--primary-500)', false],
]);
