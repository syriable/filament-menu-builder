<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

/**
 * A single broken rule, optionally pointing at the offending node and field.
 */
final readonly class TreeViolation
{
    public function __construct(
        public string $message,
        public ?string $key = null,
        public ?string $field = null,
    ) {}
}
