<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Syriable\Filament\Plugins\MenuBuilder\Actions\CreateMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\UnknownPlacement;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuVisibility;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Category;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\User;

beforeEach(function (): void {
    registerTestPlacements();
});

/**
 * @param  iterable<ResolvedMenuItem>  $items
 * @return array<int|string, mixed>
 */
function labels(iterable $items): array
{
    $labels = [];

    foreach ($items as $item) {
        $labels[] = $item->hasChildren() ? [$item->label => labels($item->children)] : $item->label;
    }

    return $labels;
}

it('builds the hierarchy of one placement', function (): void {
    Menu::sync('header', [
        linkItem('Home', '/'),
        linkItem('Services', '/services', ['children' => [
            linkItem('Design', '/design', ['children' => [linkItem('Logos', '/logos')]]),
            linkItem('Development', '/development'),
        ]]),
    ]);
    Menu::sync('footer', [headingItem('Company')]);

    $menu = Menu::build('header');

    expect(labels($menu))->toBe([
        'Home',
        ['Services' => [['Design' => ['Logos']], 'Development']],
    ])
        ->and($menu[1]->children[0]->children[0]->depth)->toBe(3)
        ->and(labels(Menu::build('footer')))->toBe(['Company']);
});

it('throws for unknown placements', function (): void {
    Menu::build('nowhere');
})->throws(UnknownPlacement::class);

it('returns frontend ready items', function (): void {
    Menu::sync('header', [
        linkItem('Docs', 'https://example.com/docs', [
            'icon' => 'heroicon-o-book-open',
            'color' => 'primary',
            'badge' => 'New',
            'badge_color' => 'success',
            'data' => ['link_type' => 'url', 'url' => 'https://example.com/docs', 'new_tab' => true],
        ]),
        headingItem('Resources'),
    ]);

    [$docs, $resources] = Menu::build('header')->all();

    expect($docs)->toBeInstanceOf(ResolvedMenuItem::class)
        ->and($docs->url)->toBe('https://example.com/docs')
        ->and($docs->icon)->toBe('heroicon-o-book-open')
        ->and($docs->color)->toBe('primary')
        ->and($docs->badge)->toBe('New')
        ->and($docs->badgeColor)->toBe('success')
        ->and($docs->openInNewTab)->toBeTrue()
        ->and($resources->url)->toBeNull()
        ->and($resources->hasUrl())->toBeFalse()
        ->and(json_decode((string) json_encode($docs), true))->toMatchArray(['label' => 'Docs', 'badge' => 'New']);
});

it('resolves route urls with parameters', function (): void {
    Menu::sync('header', [
        ['type' => 'link', 'label' => 'Profile', 'data' => ['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => ['user' => 123]]],
    ]);

    expect(Menu::build('header')->first()->url)->toBe('http://localhost/users/123');
});

it('hides links whose route no longer resolves', function (): void {
    Menu::sync('header', [linkItem('Home', '/')]);
    MenuItem::query()->update(['data' => ['link_type' => 'route', 'route' => 'removed-route']]);
    Menu::flushCache('header');

    expect(Menu::build('header'))->toBeEmpty();
});

it('removes inactive items with their descendants', function (): void {
    Menu::sync('header', [
        linkItem('Visible', '/'),
        linkItem('Hidden', '/hidden', ['is_active' => false, 'children' => [linkItem('Child', '/child')]]),
    ]);

    expect(labels(Menu::build('header')))->toBe(['Visible']);
});

it('filters items by visibility', function (?bool $authenticated, array $expected): void {
    Menu::sync('header', [
        linkItem('Everyone', '/'),
        linkItem('Login', '/login', ['visibility' => 'guests']),
        linkItem('Account', '/account', ['visibility' => 'authenticated', 'children' => [linkItem('Orders', '/orders')]]),
    ]);

    $user = $authenticated ? new User : null;

    expect(labels(Menu::build('header', $user)))->toBe($expected);
})->with([
    'guest' => [false, ['Everyone', 'Login']],
    'authenticated' => [true, ['Everyone', ['Account' => ['Orders']]]],
]);

it('uses the authenticated user by default', function (): void {
    Menu::sync('header', [linkItem('Account', '/account', ['visibility' => 'authenticated'])]);

    expect(Menu::build('header'))->toBeEmpty();

    $this->actingAs(admin());

    expect(labels(Menu::build('header')))->toBe(['Account']);
});

it('supports custom visibility rules', function (): void {
    Menu::registerVisibility(MenuVisibility::make('admins', 'Admins', fn (?Illuminate\Contracts\Auth\Authenticatable $user): bool => $user?->getAttribute('name') === 'Admin'));

    Menu::sync('header', [linkItem('Admin', '/admin', ['visibility' => 'admins'])]);

    expect(Menu::build('header', new User(['name' => 'Someone'])))->toBeEmpty()
        ->and(labels(Menu::build('header', new User(['name' => 'Admin']))))->toBe(['Admin']);
});

it('marks the current item and its ancestors', function (): void {
    Menu::sync('header', [
        linkItem('Home', '/'),
        linkItem('Services', '/services', ['children' => [linkItem('Design', '/services/design')]]),
    ]);

    [$home, $services] = Menu::build('header', currentUrl: 'http://localhost/services/design?page=2')->all();

    expect($home->isActive())->toBeFalse()
        ->and($services->isCurrent)->toBeFalse()
        ->and($services->isActiveTrail)->toBeTrue()
        ->and($services->children[0]->isCurrent)->toBeTrue();
});

it('resolves model backed items with one query per type', function (): void {
    Menu::registerItemType(
        MenuItemType::make('category')
            ->model(Category::class, titleAttribute: 'name')
            ->resolveUrlUsing(fn (Category $record): string => route('categories.show', $record->slug)),
    );

    $shoes = Category::query()->create(['name' => 'Shoes', 'slug' => 'shoes']);
    $hats = Category::query()->create(['name' => 'Hats', 'slug' => 'hats']);
    $deleted = Category::query()->create(['name' => 'Deleted', 'slug' => 'deleted']);

    Menu::sync('header', [
        ['type' => 'category', 'data' => ['record_id' => $shoes->id]],
        ['type' => 'category', 'label' => 'All hats', 'data' => ['record_id' => $hats->id]],
        ['type' => 'category', 'data' => ['record_id' => $deleted->id]],
    ]);

    $deleted->delete();
    Menu::build('header'); // warm the cache

    DB::enableQueryLog();
    $menu = Menu::build('header');

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(labels($menu))->toBe(['Shoes', 'All hats'])
        ->and($menu->pluck('url')->all())->toBe(['http://localhost/categories/shoes', 'http://localhost/categories/hats']);
});

it('caches the published tree per placement', function (): void {
    Menu::sync('header', [linkItem('Home', '/')]);
    Menu::build('header');

    DB::enableQueryLog();
    Menu::build('header');

    expect(DB::getQueryLog())->toBeEmpty()
        ->and(cache()->has('menu-builder.header'))->toBeTrue();
});

it('invalidates the cache of the saved placement only', function (): void {
    Menu::sync('header', [linkItem('Home', '/')]);
    Menu::sync('footer', [headingItem('Company')]);
    Menu::build('header');
    Menu::build('footer');

    app(CreateMenuItem::class)->handle('header', linkItem('About', '/about'));

    expect(cache()->has('menu-builder.header'))->toBeFalse()
        ->and(cache()->has('menu-builder.footer'))->toBeTrue()
        ->and(labels(Menu::build('header')))->toBe(['Home', 'About']);
});

it('can disable caching', function (): void {
    config()->set('menu-builder.cache.enabled', false);
    Menu::sync('header', [linkItem('Home', '/')]);

    Menu::build('header');

    expect(cache()->has('menu-builder.header'))->toBeFalse();
});

it('loads a placement with a single query', function (): void {
    config()->set('menu-builder.cache.enabled', false);
    Menu::sync('header', [linkItem('Home', '/', ['children' => [linkItem('Child', '/child')]])]);

    DB::enableQueryLog();
    Menu::build('header');

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(DB::getQueryLog()[0]['bindings'])->toBe(['header']);
});

it('builds large menus quickly', function (): void {
    config()->set('menu-builder.cache.enabled', false);

    $items = [];

    for ($i = 0; $i < 50; $i++) {
        $children = [];

        for ($j = 0; $j < 20; $j++) {
            $children[] = linkItem("Item {$i}.{$j}", "/items/{$i}/{$j}");
        }

        $items[] = linkItem("Item {$i}", "/items/{$i}", ['children' => $children]);
    }

    Menu::sync('header', $items);

    $start = microtime(true);
    $menu = Menu::build('header');

    expect($menu)->toHaveCount(50)
        ->and($menu->sum(fn (ResolvedMenuItem $item): int => count($item->children)))->toBe(1000)
        ->and(microtime(true) - $start)->toBeLessThan(2.0);
});

describe('label translations', function (): void {
    beforeEach(function (): void {
        Menu::sync('header', [
            linkItem('Pricing', '/pricing', ['data' => ['link_type' => 'url', 'url' => '/pricing', 'label_translations' => ['ar' => 'الأسعار', 'de' => '']]]),
            linkItem('About', '/about'),
        ]);
    });

    it('uses the label of the current locale', function (): void {
        app()->setLocale('ar');

        expect(Menu::build('header')->pluck('label')->all())->toBe(['الأسعار', 'About']);
    });

    it('builds the labels of an explicit locale', function (): void {
        expect(Menu::build('header', locale: 'ar')->pluck('label')->all())->toBe(['الأسعار', 'About'])
            ->and(Menu::build('header', locale: 'en')->pluck('label')->all())->toBe(['Pricing', 'About']);
    });

    it('falls back to the language and then to the default label', function (): void {
        expect(Menu::build('header', locale: 'ar_SA')->first()->label)->toBe('الأسعار')
            ->and(Menu::build('header', locale: 'de')->first()->label)->toBe('Pricing')
            ->and(Menu::build('header', locale: 'fr')->first()->label)->toBe('Pricing');
    });
});

it('resolves the text style of items', function (): void {
    Menu::sync('header', [
        linkItem('Pricing', '/pricing', ['data' => ['link_type' => 'url', 'url' => '/pricing', 'text_style' => ['weight' => 'bold', 'hover_color' => '#f59e0b']]]),
        linkItem('About', '/about'),
    ]);

    [$pricing, $about] = Menu::build('header')->all();

    expect($pricing->textStyle->toArray())->toBe(['weight' => 'bold', 'hover_color' => '#f59e0b'])
        ->and($pricing->toArray()['text_style'])->toBe(['weight' => 'bold', 'hover_color' => '#f59e0b'])
        ->and($about->textStyle->isEmpty())->toBeTrue();
});
