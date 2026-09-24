<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

/**
 * Screen widths for responsive item visibility, matching Tailwind's
 * default breakpoints.
 */
enum Breakpoint: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case TwoXl = '2xl';

    /**
     * The minimum screen width of the breakpoint, in pixels.
     */
    public function pixels(): int
    {
        return match ($this) {
            self::Sm => 640,
            self::Md => 768,
            self::Lg => 1024,
            self::Xl => 1280,
            self::TwoXl => 1536,
        };
    }

    public function getLabel(): string
    {
        $label = __('menu-builder::menu-builder.breakpoints.'.$this->value, ['width' => $this->pixels()]);

        return is_string($label) ? $label : $this->value;
    }
}
