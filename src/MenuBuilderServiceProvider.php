<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore;
use Syriable\Filament\Plugins\MenuBuilder\Drafts\CacheDraftStore;

class MenuBuilderServiceProvider extends PackageServiceProvider
{
    public static string $name = 'menu-builder';

    public static string $viewNamespace = 'menu-builder';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigration('create_menu_items_table')
            ->hasInstallCommand(static function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(MenuRegistry::class, static function (Application $app): MenuRegistry {
            /** @var array<string, array<string, mixed>> $placements */
            $placements = $app->make('config')->get('menu-builder.placements', []);

            return new MenuRegistry($placements);
        });

        $this->app->singleton(MenuManager::class);

        $this->app->singleton(DraftStore::class, static function (Application $app): DraftStore {
            $config = $app->make('config');
            $store = $config->get('menu-builder.drafts.store');

            return new CacheDraftStore(
                $app->make(CacheFactory::class)->store(is_string($store) ? $store : null),
                (int) $config->get('menu-builder.drafts.ttl', 60 * 60 * 12),
            );
        });
    }

    #[Override]
    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('menu-builder', __DIR__.'/../resources/dist/menu-builder.css'),
            AlpineComponent::make('menu-builder-tree', __DIR__.'/../resources/dist/menu-builder-tree.js'),
        ], package: 'syriable/filament-menu-builder');
    }
}
