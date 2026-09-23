<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\ItemTypes;

use Override;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;

/**
 * A plain text item without a URL, typically used to group links.
 */
class HeadingType extends MenuItemType
{
    public const string KEY = 'heading';

    #[Override]
    public static function make(string $key = self::KEY): static
    {
        return parent::make($key);
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->label ?? MenuRegistry::translate('types.heading');
    }

    #[Override]
    protected function setUp(): void
    {
        $this
            ->icon('heroicon-o-hashtag')
            ->withoutUrl();
    }
}
