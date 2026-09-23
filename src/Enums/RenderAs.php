<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

/**
 * The root element a menu item is rendered as.
 */
enum RenderAs: string
{
    case Link = 'link';
    case Button = 'button';
    case Heading = 'heading';

    public function getLabel(): string
    {
        $label = __('menu-builder::menu-builder.render_as.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
