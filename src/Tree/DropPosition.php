<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

/**
 * Where an item is dropped relative to a target item.
 */
enum DropPosition: string
{
    case Before = 'before';
    case After = 'after';
    case Inside = 'inside';
}
