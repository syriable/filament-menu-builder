<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\Concerns;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuAuthorizer;

trait InteractsWithMenuPlugin
{
    /**
     * The cluster of this page, set by the plugin when it registers the
     * page. A static of its own: Page::$cluster is shared by every page.
     *
     * @var class-string<Cluster>|null
     */
    protected static ?string $menuBuilderCluster = null;

    /**
     * @param  class-string<Cluster>|null  $cluster
     */
    public static function useCluster(?string $cluster): void
    {
        static::$menuBuilderCluster = $cluster;
    }

    /**
     * @return class-string<Cluster>|null
     */
    public static function getCluster(): ?string
    {
        return static::$menuBuilderCluster;
    }

    /**
     * Prepends the resource and cluster the plugin places the pages in.
     *
     * @param  array<int|string, string>  $breadcrumbs
     * @return array<int|string, string>
     */
    protected static function withParentBreadcrumbs(array $breadcrumbs): array
    {
        $resource = static::plugin()?->getResource();

        if ($resource !== null && $resource::hasPage('index')) {
            $breadcrumbs = [$resource::getUrl() => $resource::getBreadcrumb(), ...$breadcrumbs];
        }

        $cluster = static::getCluster();

        if ($cluster === null) {
            return $breadcrumbs;
        }

        /** @var array<string, string> $breadcrumbs Filament types breadcrumbs by URL; the last entry may be a list item. */
        return $cluster::unshiftClusterBreadcrumbs($breadcrumbs);
    }

    protected static function plugin(): ?MenuBuilderPlugin
    {
        $plugin = Filament::getCurrentOrDefaultPanel()?->getPlugins()['menu-builder'] ?? null;

        return $plugin instanceof MenuBuilderPlugin ? $plugin : null;
    }

    /**
     * The plugin of the current panel, or one configured from the config file
     * when the page is used without it.
     */
    protected static function settings(): MenuBuilderPlugin
    {
        return static::plugin() ?? MenuBuilderPlugin::make();
    }

    protected static function registry(): MenuRegistry
    {
        return app(MenuRegistry::class);
    }

    protected static function authorizer(): MenuAuthorizer
    {
        return app(MenuAuthorizer::class);
    }

    /**
     * The Filament Shield permission of this page, e.g. `View:MenuPlacements`,
     * or null when Shield is not used or excludes the page.
     */
    public static function getShieldPermission(): ?string
    {
        if (! static::settings()->usesShield()) {
            return null;
        }

        $page = FilamentShield::getPages()[static::class] ?? null;
        $permissions = is_array($page) && is_array($page['permissions'] ?? null) ? $page['permissions'] : [];
        $permission = array_key_first($permissions);

        return is_string($permission) ? $permission : null;
    }

    /**
     * Whether the current user has the Shield permission of this page.
     */
    protected static function passesShield(): bool
    {
        $permission = static::getShieldPermission();

        if ($permission === null) {
            return true;
        }

        return Filament::auth()->user()?->can($permission) ?? false;
    }

    /**
     * Whether the current user may view at least one placement.
     */
    protected static function canViewAnyPlacement(): bool
    {
        foreach (array_keys(static::registry()->placements()) as $placement) {
            if (static::authorizer()->can(MenuAuthorizer::VIEW_ANY, $placement)) {
                return true;
            }
        }

        return false;
    }
}
