<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Resources\Resource as FilamentResource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
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

    /** @var array<int|string, string>|null */
    protected ?array $locales = null;

    protected ?bool $slideOver = null;

    protected Width|string|null $modalWidth = null;

    /** @var class-string<Cluster>|null */
    protected ?string $cluster = null;

    /** @var class-string<FilamentResource>|null */
    protected ?string $resource = null;

    protected ?string $navigationParentItem = null;

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
        // Filament reads the cluster while it registers the pages, before any request.
        MenuPlacements::useCluster($this->getCluster());
        ManageMenu::useCluster($this->getCluster());

        $panel->pages([
            MenuPlacements::class,
            ManageMenu::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    /**
     * Places the menu pages in a cluster: they use its URL prefix, appear in
     * its sub-navigation and breadcrumbs, and not in the main navigation.
     *
     * @param  string|null  $cluster  A class extending Filament\Clusters\Cluster.
     */
    public function cluster(?string $cluster): static
    {
        if ($cluster !== null && ! is_a($cluster, Cluster::class, allow_string: true)) {
            throw new InvalidArgumentException("[{$cluster}] is not a Filament cluster.");
        }

        $this->cluster = $cluster;

        return $this;
    }

    /**
     * Shows the menu pages under a resource: the navigation item is nested
     * below the resource's item (same group, and its cluster when it has
     * one), and the resource leads the breadcrumbs.
     *
     * @param  string|null  $resource  A class extending Filament\Resources\Resource.
     */
    public function resource(?string $resource): static
    {
        if ($resource !== null && ! is_a($resource, FilamentResource::class, allow_string: true)) {
            throw new InvalidArgumentException("[{$resource}] is not a Filament resource.");
        }

        $this->resource = $resource;

        return $this;
    }

    /**
     * Nests the navigation item below another navigation item, by its label.
     */
    public function navigationParentItem(?string $label): static
    {
        $this->navigationParentItem = $label;

        return $this;
    }

    /**
     * @return class-string<Cluster>|null
     */
    public function getCluster(): ?string
    {
        return $this->cluster ?? ($this->resource === null ? null : $this->resource::getCluster());
    }

    /**
     * @return class-string<FilamentResource>|null
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function getNavigationParentItem(): ?string
    {
        return $this->navigationParentItem ?? ($this->resource === null ? null : $this->resource::getNavigationLabel());
    }

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

    /**
     * Locales the item labels can be translated into, e.g. ['en', 'ar'] or
     * ['en' => 'English', 'ar' => 'العربية']. Overrides `menu-builder.locales`.
     *
     * @param  array<int|string, string>|null  $locales
     */
    public function locales(?array $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * Whether the item form opens in a slide-over or a centered modal.
     * Overrides `menu-builder.item_form.slide_over`.
     */
    public function slideOver(?bool $condition = true): static
    {
        $this->slideOver = $condition;

        return $this;
    }

    /**
     * Width of the item form, e.g. Width::ThreeExtraLarge or '3xl'.
     * Overrides `menu-builder.item_form.width`.
     */
    public function modalWidth(Width|string|null $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    /**
     * @return array<string, string> Locale => label.
     */
    public function getLocales(): array
    {
        $locales = $this->locales ?? config('menu-builder.locales', []);
        $normalized = [];

        foreach (is_array($locales) ? $locales : [] as $locale => $label) {
            if (! is_string($label) || $label === '') {
                continue;
            }

            is_int($locale) ? $normalized[$label] = $label : $normalized[$locale] = $label;
        }

        return $normalized;
    }

    public function hasSlideOver(): bool
    {
        return $this->slideOver ?? (bool) config('menu-builder.item_form.slide_over', true);
    }

    public function getModalWidth(): Width
    {
        $width = $this->modalWidth ?? config('menu-builder.item_form.width');

        if ($width instanceof Width) {
            return $width;
        }

        return (is_string($width) ? Width::tryFrom($width) : null) ?? Width::TwoExtraLarge;
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->shouldRegisterNavigation;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup ?? ($this->resource === null ? null : $this->resource::getNavigationGroup());
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
