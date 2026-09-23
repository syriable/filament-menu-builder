<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Exceptions;

final class UnknownPlacement extends MenuBuilderException
{
    public static function make(string $key): self
    {
        return new self("Menu placement [{$key}] is not registered.");
    }
}
