<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Enums\MegaColumns;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\MegaCategoryType;
use Syriable\Filament\Plugins\MenuBuilder\MegaMenu;

arch('the mega variant classes use strict types')
    ->expect([MegaMenu::class, MegaColumns::class, MegaCategoryType::class])
    ->toUseStrictTypes();

arch('the mega menu registrar is final')
    ->expect(MegaMenu::class)
    ->toBeFinal();

arch('the core does not depend on the mega variant')
    ->expect('Syriable\Filament\Plugins\MenuBuilder')
    ->not->toUse([MegaMenu::class, MegaCategoryType::class, MegaColumns::class])
    ->ignoring([MegaMenu::class, MegaCategoryType::class, MegaColumns::class]);
