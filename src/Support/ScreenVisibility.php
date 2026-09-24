<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\Rule;
use Syriable\Filament\Plugins\MenuBuilder\Enums\Breakpoint;

/**
 * On which screen sizes an item is shown, stored in `data.screens`:
 * `from` shows it from that breakpoint up, `until` hides it from that
 * breakpoint up. Both together give a range, e.g. tablets only.
 *
 * The frontend renders it as `mb-show-from-*` / `mb-hide-from-*` classes on
 * the item's wrapper, backed by media queries in menu.css, so the whole
 * entry (with its dropdown or children) appears and disappears.
 *
 * @implements Arrayable<string, string>
 */
final readonly class ScreenVisibility implements Arrayable
{
    public function __construct(
        public ?Breakpoint $from = null,
        public ?Breakpoint $until = null,
    ) {}

    public static function fromArray(mixed $screens): self
    {
        if (! is_array($screens)) {
            return new self;
        }

        $from = is_string($screens['from'] ?? null) ? Breakpoint::tryFrom($screens['from']) : null;
        $until = is_string($screens['until'] ?? null) ? Breakpoint::tryFrom($screens['until']) : null;

        // A range that can never match is ignored rather than hiding the item everywhere.
        if ($from !== null && $until !== null && $until->pixels() <= $from->pixels()) {
            return new self;
        }

        return new self($from, $until);
    }

    /**
     * Validation rules for the tree guard, keyed relative to `data`.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $from = is_string($value['from'] ?? null) ? Breakpoint::tryFrom($value['from']) : null;
                $until = is_string($value['until'] ?? null) ? Breakpoint::tryFrom($value['until']) : null;

                if ($from !== null && $until !== null && $until->pixels() <= $from->pixels()) {
                    $fail(__('menu-builder::menu-builder.validation.screens'));
                }
            }],
            $prefix.'.from' => ['nullable', Rule::enum(Breakpoint::class)],
            $prefix.'.until' => ['nullable', Rule::enum(Breakpoint::class)],
        ];
    }

    public function isAlways(): bool
    {
        return $this->from === null && $this->until === null;
    }

    /**
     * Whether the item is shown on a screen of the given width in pixels.
     */
    public function showsAt(int $width): bool
    {
        return ($this->from === null || $width >= $this->from->pixels())
            && ($this->until === null || $width < $this->until->pixels());
    }

    /**
     * @return list<string>
     */
    public function classes(): array
    {
        return array_values(array_filter([
            $this->from === null ? null : 'mb-show-from-'.$this->from->value,
            $this->until === null ? null : 'mb-hide-from-'.$this->until->value,
        ]));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from?->value,
            'until' => $this->until?->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
