<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Enums\MegaColumns;

/**
 * @param  array<string, mixed>  $data
 */
function megaColumnsCategory(array $data = [], int $groups = 0): ResolvedMenuItem
{
    $children = [];

    for ($i = 1; $i <= $groups; $i++) {
        $children[] = new ResolvedMenuItem(id: 100 + $i, type: 'heading', label: "Group {$i}", url: null, depth: 2);
    }

    return new ResolvedMenuItem(
        id: 1,
        type: 'mega-category',
        label: 'Design',
        url: '/design',
        depth: 1,
        data: $data,
        children: $children,
    );
}

describe('MegaColumns', function (): void {
    it('uses the stored column count', function (mixed $stored, MegaColumns $expected): void {
        expect(MegaColumns::for(megaColumnsCategory(['columns' => $stored], groups: 1)))->toBe($expected);
    })->with([
        'integer' => [3, MegaColumns::Three],
        'numeric string from a select' => ['4', MegaColumns::Four],
        'one' => [1, MegaColumns::One],
    ]);

    it('falls back to one column per group, clamped to one to four', function (int $groups, MegaColumns $expected): void {
        expect(MegaColumns::for(megaColumnsCategory(groups: $groups)))->toBe($expected);
    })->with([
        'no groups' => [0, MegaColumns::One],
        'one group' => [1, MegaColumns::One],
        'three groups' => [3, MegaColumns::Three],
        'six groups' => [6, MegaColumns::Four],
    ]);

    it('ignores invalid stored values', function (mixed $stored): void {
        expect(MegaColumns::for(megaColumnsCategory(['columns' => $stored], groups: 2)))->toBe(MegaColumns::Two);
    })->with([
        'too many' => [5],
        'zero' => [0],
        'negative string' => ['-2'],
        'decimal string' => ['2.5'],
        'text' => ['wide'],
        'null' => [null],
        'array' => [[3]],
    ]);

    it('offers every count as a form option', function (): void {
        expect(MegaColumns::options())->toBe([1 => '1', 2 => '2', 3 => '3', 4 => '4']);
    });
});
