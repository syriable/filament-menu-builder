<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishResult;

/**
 * Dispatched after a placement was persisted and its cache was cleared.
 */
final readonly class MenuPublished
{
    use Dispatchable;

    public function __construct(
        public string $placement,
        public PublishResult $result,
    ) {}
}
