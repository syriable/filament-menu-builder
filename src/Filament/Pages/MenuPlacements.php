<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\Concerns\InteractsWithMenuPlugin;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuAuthorizer;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use UnitEnum;

/**
 * Lists every registered placement with a link to its editor.
 */
class MenuPlacements extends Page
{
    use InteractsWithMenuPlugin;

    protected static ?string $slug = 'menus';

    protected string $view = 'menu-builder::filament.pages.menu-placements';

    #[Override]
    public static function canAccess(): bool
    {
        return static::passesShield() && static::canViewAnyPlacement();
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return (static::plugin()?->shouldRegisterNavigation() ?? true) && parent::shouldRegisterNavigation();
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return static::plugin()?->getNavigationLabel() ?? __('menu-builder::menu-builder.navigation.label');
    }

    #[Override]
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()?->getNavigationIcon() ?? parent::getNavigationIcon();
    }

    #[Override]
    public static function getNavigationParentItem(): ?string
    {
        return static::plugin()?->getNavigationParentItem() ?? parent::getNavigationParentItem();
    }

    /**
     * @return array<int|string, string>
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = static::withParentBreadcrumbs([]);

        return $breadcrumbs === [] ? [] : [...$breadcrumbs, $this->getTitle() instanceof Htmlable ? $this->getTitle()->toHtml() : $this->getTitle()];
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    #[Override]
    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return __('menu-builder::menu-builder.navigation.label');
    }

    /**
     * @return list<array{placement: MenuPlacement, count: int, url: string}>
     */
    public function getPlacements(): array
    {
        $placements = array_filter(
            static::registry()->placements(),
            static fn (MenuPlacement $placement): bool => static::authorizer()->can(MenuAuthorizer::VIEW_ANY, $placement->getKey()),
        );

        $counts = app(MenuRepository::class)->query()
            ->whereIn('placement', array_keys($placements))
            ->toBase()
            ->selectRaw('placement, count(*) as aggregate')
            ->groupBy('placement')
            ->pluck('aggregate', 'placement');

        return array_values(array_map(static fn (MenuPlacement $placement): array => [
            'placement' => $placement,
            'count' => (int) ($counts[$placement->getKey()] ?? 0),
            'url' => ManageMenu::getUrl(['placement' => $placement->getKey()]),
        ], $placements));
    }
}
