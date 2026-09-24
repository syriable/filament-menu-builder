<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Syriable\Filament\Plugins\IconHub\IconHubServiceProvider;
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderServiceProvider;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\TestPanelProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            IconHubServiceProvider::class,
            MenuBuilderServiceProvider::class,
            $this->panelProvider(),
        ];
    }

    /**
     * @return class-string
     */
    protected function panelProvider(): string
    {
        return TestPanelProvider::class;
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_public')->default(true);
        });

        (require __DIR__.'/../database/migrations/create_menu_items_table.php.stub')->up();
    }

    #[Override]
    protected function defineRoutes($router): void
    {
        Route::get('/', fn () => 'home')->name('home');
        Route::get('/about', fn () => 'about')->name('about');
        Route::get('/users/{user}', fn () => 'user')->name('users.show');
        Route::get('/categories/{category:slug}', fn () => 'category')->name('categories.show');
        Route::post('/contact', fn () => 'contact')->name('contact.store');
    }
}
