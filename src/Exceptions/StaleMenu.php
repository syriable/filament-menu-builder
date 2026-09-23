<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Exceptions;

/**
 * The published menu changed after the draft was started, e.g. because
 * another administrator saved the same placement.
 */
final class StaleMenu extends MenuBuilderException
{
    public static function make(string $placement): self
    {
        return new self("The [{$placement}] menu was changed by someone else after you started editing it.");
    }
}
