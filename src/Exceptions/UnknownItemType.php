<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Exceptions;

final class UnknownItemType extends MenuBuilderException
{
    public static function make(string $key): self
    {
        return new self("Menu item type [{$key}] is not registered.");
    }
}
