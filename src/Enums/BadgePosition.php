<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

/**
 * Logical badge positions: start and end follow the document direction,
 * so the same data works in LTR and RTL.
 */
enum BadgePosition: string
{
    case Start = 'start';
    case End = 'end';
    case Top = 'top';

    public function getLabel(): string
    {
        $label = __('menu-builder::menu-builder.badge_position.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
