<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;

/**
 * The number of columns a mega menu panel lays its groups out in.
 */
enum MegaColumns: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;

    /**
     * The columns of a category: the value stored in `data.columns`, or one
     * column per group (at most four) when none is set.
     */
    public static function for(ResolvedMenuItem $item): self
    {
        return self::fromData($item->data['columns'] ?? null)
            ?? self::fromCount(count($item->children));
    }

    /**
     * Reads a stored value. Form selects submit numeric strings, seeders and
     * the API usually integers. Anything else is ignored.
     */
    public static function fromData(mixed $value): ?self
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) ? self::tryFrom($value) : null;
    }

    public static function fromCount(int $count): self
    {
        return self::from(max(self::One->value, min(self::Four->value, $count)));
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = (string) $case->value;
        }

        return $options;
    }
}
