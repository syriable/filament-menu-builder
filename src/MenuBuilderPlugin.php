<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\MenuPlacements;
use UnitEnum;

/**
 * Registers the menu editor pages in a Filament panel.
 *
 * Placements and item types are registered independently of any panel
 * (see the Menu facade), so menus can also be built where no panel exists.
 */
class MenuBuilderPlugin implements Plugin
{
    protected bool $shouldRegisterNavigation = true;

    protected string|UnitEnum|null $navigationGroup = null;

    protected string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected ?string $navigationLabel = null;

    protected ?int $navigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'menu-builder';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            MenuPlacements::class,
            ManageMenu::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public function navigation(bool $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->shouldRegisterNavigation;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->navigationIcon;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
