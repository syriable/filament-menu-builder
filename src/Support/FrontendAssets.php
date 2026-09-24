<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

/**
 * The frontend menu's structural CSS and dropdown script. They are printed
 * inline once per page by <x-menu-builder::menu>, so no build step or asset
 * publishing is needed. Disable with `menu-builder.frontend.assets` to ship
 * your own.
 */
final class FrontendAssets
{
    /** @var array<string, string> */
    private static array $contents = [];

    public static function css(): string
    {
        return self::read('menu.css');
    }

    public static function js(): string
    {
        return self::read('menu.js');
    }

    /**
     * Styles of the "mega" variant, printed only by menus that use it.
     */
    public static function megaCss(): string
    {
        return self::read('mega.css');
    }

    private static function read(string $file): string
    {
        return self::$contents[$file] ??= (string) file_get_contents(__DIR__.'/../../resources/dist/frontend/'.$file);
    }
}
