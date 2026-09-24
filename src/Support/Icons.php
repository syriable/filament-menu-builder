<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Providers\BladeIconSetProvider;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;
use Throwable;

/**
 * Item icons come in two formats:
 *
 * - Icon Hub identifiers, `provider:name` (e.g. `heroicons:o-home`), stored
 *   by the IconSelect field of the item form. They can come from any
 *   provider: Blade Icons sets, local SVGs, uploads or remote APIs.
 * - Blade Icons names (e.g. `heroicon-o-home`), used by items created before
 *   the picker, by seeders and by item types.
 *
 * Filament's components throw for unknown icons, so the menu only passes
 * icons that exist.
 */
final class Icons
{
    /** @var array<string, bool> */
    private static array $bladeIcons = [];

    /**
     * The icon in a form Filament's components accept, or null when it does
     * not exist.
     */
    public static function safe(?string $icon): string|Htmlable|null
    {
        if ($icon === null || $icon === '') {
            return null;
        }

        if (self::isHubId($icon)) {
            $found = IconHub::find($icon);

            return $found === null ? null : IconHub::html($found);
        }

        return (self::$bladeIcons[$icon] ??= self::bladeIconExists($icon)) ? $icon : null;
    }

    /**
     * The Icon Hub identifier of an icon, converting Blade Icons names of
     * registered sets (`heroicon-o-home` becomes `heroicons:o-home`). Other
     * values are returned unchanged.
     */
    public static function toHubId(?string $icon): ?string
    {
        if ($icon === null || $icon === '' || self::isHubId($icon)) {
            return $icon === '' ? null : $icon;
        }

        foreach (IconHub::all() as $id => $provider) {
            if ($provider instanceof BladeIconSetProvider && str_starts_with($icon, $provider->prefix().'-')) {
                return $id.':'.substr($icon, strlen($provider->prefix()) + 1);
            }
        }

        return $icon;
    }

    public static function isHubId(string $icon): bool
    {
        return str_contains($icon, ':') && IconId::tryParse($icon) !== null;
    }

    private static function bladeIconExists(string $icon): bool
    {
        try {
            svg($icon);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
