<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * Validates and normalizes the arbitrary HTML attributes of menu items.
 *
 * Any attribute name is accepted as long as it cannot break out of the
 * attribute syntax (no whitespace, quotes, "<", ">", "/" or "="), so
 * class, id, data-*, aria-*, x-on:click, @click, :class and wire:click
 * all work. Values are escaped when the attribute bag is built.
 */
final class HtmlAttributes
{
    /**
     * HTML boolean attributes: "false", "0", "off" and "no" remove them.
     */
    public const array BOOLEAN = [
        'allowfullscreen', 'async', 'autofocus', 'autoplay', 'checked', 'controls', 'default', 'defer',
        'disabled', 'formnovalidate', 'hidden', 'inert', 'ismap', 'itemscope', 'loop', 'multiple', 'muted',
        'nomodule', 'novalidate', 'open', 'playsinline', 'readonly', 'required', 'reversed', 'selected',
    ];

    private const string NAME_PATTERN = '/^[^\s"\'<>\/=\x00-\x1F\x7F]+$/u';

    public static function isValidName(mixed $name): bool
    {
        return is_string($name) && $name !== '' && mb_strlen($name) <= 100 && preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * Normalizes stored attributes: invalid names are dropped, empty values
     * become bare attributes and boolean attributes can be switched off.
     *
     * @return array<string, string|true>
     */
    public static function normalize(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $normalized = [];

        foreach ($attributes as $name => $value) {
            $name = is_string($name) ? trim($name) : $name;

            if (! self::isValidName($name) || ! (is_scalar($value) || $value === null)) {
                continue;
            }

            /** @var string $name */
            if ($value === false || $value === null) {
                continue;
            }

            $value = $value === true ? '' : trim((string) $value);

            if (in_array(strtolower($name), self::BOOLEAN, true)) {
                if (in_array(strtolower($value), ['false', '0', 'off', 'no'], true)) {
                    continue;
                }

                $normalized[$name] = true;

                continue;
            }

            $normalized[$name] = $value === '' ? true : $value;
        }

        return $normalized;
    }

    /**
     * Builds an attribute bag with escaped values. ComponentAttributeBag does
     * not escape values it did not receive from compiled Blade, so this is
     * the only safe way to render runtime attributes.
     *
     * @param  array<string, string|true>  $attributes
     */
    public static function bag(array $attributes): ComponentAttributeBag
    {
        return new ComponentAttributeBag(array_map(
            static fn (string|bool $value): string|bool => is_string($value) ? e($value) : $value,
            $attributes,
        ));
    }
}
