<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Closure;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\HeadingType;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\LinkType;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\MegaCategoryType;

/**
 * Opt-in registration for the `mega` frontend variant.
 *
 * The preset placement has the shape the variant renders: categories at the
 * root, groups below them (a heading, or a link for a clickable group title)
 * and links inside the groups. The tree guard enforces it on every write path.
 */
final class MegaMenu
{
    public const string PLACEMENT = 'categories';

    /**
     * Registers the mega category item type (once) and a mega menu placement.
     *
     * @param  (Closure(MenuPlacement): mixed)|null  $configure  Adjusts the preset, e.g. its label or the types allowed in groups. It may return the placement or change it in place.
     */
    public static function register(string $placement = self::PLACEMENT, ?Closure $configure = null): void
    {
        $registry = app(MenuRegistry::class);

        if (! $registry->hasItemType(MegaCategoryType::KEY)) {
            $registry->registerItemType(self::itemType());
        }

        $definition = self::placement($placement);

        if ($configure !== null) {
            $configured = $configure($definition);

            if ($configured instanceof MenuPlacement) {
                $definition = $configured;
            }
        }

        $registry->registerPlacement($definition);
    }

    /**
     * The preset placement, for applications that register it themselves.
     */
    public static function placement(string $key = self::PLACEMENT): MenuPlacement
    {
        return MenuPlacement::make($key)
            ->icon('heroicon-o-squares-2x2')
            ->maxDepth(3)
            ->rootItemTypes([MegaCategoryType::KEY])
            ->childItemTypes(MegaCategoryType::KEY, [HeadingType::KEY, LinkType::KEY])
            ->childItemTypes(HeadingType::KEY, [LinkType::KEY])
            ->childItemTypes(LinkType::KEY, [LinkType::KEY]);
    }

    public static function itemType(): MegaCategoryType
    {
        return MegaCategoryType::make();
    }
}
