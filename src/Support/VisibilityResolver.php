<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;

/**
 * Decides whether an item is shown to a visitor. Unknown visibility rules
 * hide the item (fail closed).
 */
final readonly class VisibilityResolver
{
    public function __construct(private MenuRegistry $registry) {}

    public function isVisible(MenuNode $item, ?Authenticatable $user): bool
    {
        return $this->registry->visibility($item->visibility)?->allows($user, $item) ?? false;
    }
}
