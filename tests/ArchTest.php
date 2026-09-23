<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('source files use strict types')
    ->expect('Syriable\Filament\Plugins\MenuBuilder')
    ->toUseStrictTypes();

arch('exceptions extend the package exception')
    ->expect('Syriable\Filament\Plugins\MenuBuilder\Exceptions')
    ->toExtend(Syriable\Filament\Plugins\MenuBuilder\Exceptions\MenuBuilderException::class)
    ->ignoring(Syriable\Filament\Plugins\MenuBuilder\Exceptions\MenuBuilderException::class);

arch('the tree engine does not depend on Filament')
    ->expect('Syriable\Filament\Plugins\MenuBuilder\Tree')
    ->not->toUse('Filament');

arch('actions are final')
    ->expect('Syriable\Filament\Plugins\MenuBuilder\Actions')
    ->classes()
    ->toBeFinal();
