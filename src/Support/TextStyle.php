<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Closure;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\Rule;

/**
 * Text options of one menu item (weight, size, italic, underline, letter
 * case, cursor and hover color), stored in `data.text_style`.
 *
 * Invalid or unknown values are ignored, so rows written around the tree
 * guard can never break the page or inject CSS. The frontend turns the
 * options into `mb-*` classes (see resources/dist/frontend/menu.css) and a
 * `--mb-item-hover-color` custom property.
 *
 * @implements Arrayable<string, string|bool>
 */
final readonly class TextStyle implements Arrayable
{
    public const array SIZES = ['xs', 'sm', 'base', 'lg', 'xl'];

    public const array UNDERLINES = ['always', 'hover', 'none'];

    public const array TRANSFORMS = ['uppercase', 'lowercase', 'capitalize'];

    public const array CURSORS = ['pointer', 'default'];

    public function __construct(
        public ?FontWeight $weight = null,
        public ?string $size = null,
        public bool $italic = false,
        public ?string $underline = null,
        public ?string $transform = null,
        public ?string $cursor = null,
        public ?string $hoverColor = null,
    ) {}

    public static function fromArray(mixed $options): self
    {
        if (! is_array($options)) {
            return new self;
        }

        $pick = static fn (string $key, array $allowed): ?string => in_array($options[$key] ?? null, $allowed, true) ? $options[$key] : null;
        $weight = $options['weight'] ?? null;
        $hoverColor = $options['hover_color'] ?? null;

        return new self(
            weight: is_string($weight) ? FontWeight::tryFrom($weight) : null,
            size: $pick('size', self::SIZES),
            italic: filter_var($options['italic'] ?? false, FILTER_VALIDATE_BOOLEAN),
            underline: $pick('underline', self::UNDERLINES),
            transform: $pick('transform', self::TRANSFORMS),
            cursor: $pick('cursor', self::CURSORS),
            hoverColor: is_string($hoverColor) && self::isColor($hoverColor) ? trim($hoverColor) : null,
        );
    }

    /**
     * Hex, rgb(a), hsl(a) and oklch colors. Nothing that could close the
     * declaration or the style attribute.
     */
    public static function isColor(string $value): bool
    {
        return preg_match('/^\s*(#[0-9a-f]{3,8}|(rgba?|hsla?|oklch)\(\s*[0-9.,%\s\/-]+\))\s*$/i', $value) === 1;
    }

    /**
     * Validation rules for the tree guard, keyed relative to `data`.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array'],
            $prefix.'.weight' => ['nullable', Rule::enum(FontWeight::class)],
            $prefix.'.size' => ['nullable', Rule::in(self::SIZES)],
            $prefix.'.italic' => ['nullable', 'boolean'],
            $prefix.'.underline' => ['nullable', Rule::in(self::UNDERLINES)],
            $prefix.'.transform' => ['nullable', Rule::in(self::TRANSFORMS)],
            $prefix.'.cursor' => ['nullable', Rule::in(self::CURSORS)],
            $prefix.'.hover_color' => ['nullable', 'string', 'max:100', static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! self::isColor($value)) {
                    $fail(__('menu-builder::menu-builder.validation.color'));
                }
            }],
        ];
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * @return list<string>
     */
    public function classes(): array
    {
        return array_values(array_filter([
            $this->weight === null ? null : 'mb-weight-'.$this->weight->value,
            $this->size === null ? null : 'mb-text-'.$this->size,
            $this->italic ? 'mb-italic' : null,
            $this->underline === null ? null : 'mb-underline-'.$this->underline,
            $this->transform === null ? null : 'mb-transform-'.$this->transform,
            $this->cursor === null ? null : 'mb-cursor-'.$this->cursor,
            $this->hoverColor === null ? null : 'mb-hover-color',
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function styles(): array
    {
        return $this->hoverColor === null ? [] : ['--mb-item-hover-color: '.$this->hoverColor];
    }

    /**
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return array_filter([
            'weight' => $this->weight?->value,
            'size' => $this->size,
            'italic' => $this->italic,
            'underline' => $this->underline,
            'transform' => $this->transform,
            'cursor' => $this->cursor,
            'hover_color' => $this->hoverColor,
        ], static fn (string|bool|null $value): bool => $value !== null && $value !== false);
    }
}
