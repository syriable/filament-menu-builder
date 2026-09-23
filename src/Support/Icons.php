<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Throwable;

/**
 * Icon names entered by administrators are free text. Filament's components
 * throw for unknown icons, so the menu only passes icons that exist.
 */
final class Icons
{
    /** @var array<string, bool> */
    private static array $exists = [];

    public static function safe(?string $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return null;
        }

        return (self::$exists[$icon] ??= self::exists($icon)) ? $icon : null;
    }

    private static function exists(string $icon): bool
    {
        try {
            svg($icon);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
