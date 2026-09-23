<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

/**
 * Which rendered element receives an item's custom HTML attributes: the
 * item itself (<a>, <button> or heading) or its list item wrapper (<li>).
 */
enum AttributeTarget: string
{
    case Item = 'item';
    case Wrapper = 'wrapper';

    public function getLabel(): string
    {
        $label = __('menu-builder::menu-builder.attribute_target.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
