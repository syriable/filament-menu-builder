<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Exceptions;

final class DraftNotFound extends MenuBuilderException
{
    public static function make(string $id): self
    {
        return new self("Menu draft [{$id}] does not exist or has expired.");
    }
}
