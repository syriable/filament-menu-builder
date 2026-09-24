<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlParameters;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\User;

beforeEach(function (): void {
    registerTestPlacements();
});

function routeItem(string $label, string $route, array $parameters): array
{
    return ['type' => 'link', 'label' => $label, 'data' => ['link_type' => 'route', 'route' => $route, 'route_parameters' => $parameters]];
}

/**
 * @return array<string, string|null>
 */
function menuUrls(?Authenticatable $user = null): array
{
    return Menu::build('header', $user)->mapWithKeys(fn ($item): array => [$item->label => $item->url])->all();
}

it('fills route parameters with the signed-in user', function (): void {
    Menu::sync('header', [
        routeItem('My profile', 'users.show', ['user' => '{user}']),
        linkItem('Settings', '/users/{user}/settings'),
    ]);

    $user = admin();
    $this->actingAs($user);

    expect(menuUrls())->toBe([
        'My profile' => url('/users/'.$user->getKey()),
        'Settings' => '/users/'.$user->getKey().'/settings',
    ]);
});

it('hides links whose placeholders have no value', function (): void {
    Menu::sync('header', [
        routeItem('My profile', 'users.show', ['user' => '{user}']),
        linkItem('Settings', '/users/{user}/settings'),
        linkItem('About', '/about'),
    ]);

    expect(menuUrls())->toBe(['About' => '/about']);
});

it('builds the links of the given user', function (): void {
    Menu::sync('header', [routeItem('Profile', 'users.show', ['user' => '{user}'])]);

    $this->actingAs(admin());
    $other = admin();

    expect(menuUrls($other))->toBe(['Profile' => url('/users/'.$other->getKey())]);
});

it('uses user attributes but never hidden ones', function (): void {
    Menu::sync('header', [
        linkItem('By name', '/people/{user.name}'),
        linkItem('Secret', '/leak/{user.password}'),
    ]);

    $user = admin();
    $user->name = 'Jane Doe';
    $user->setHidden(['password']);
    $this->actingAs($user);

    expect(menuUrls())->toBe(['By name' => '/people/Jane%20Doe']);
});

it('uses parameters of the current route and query string', function (): void {
    Menu::sync('header', [
        routeItem('Follow', 'users.show', ['user' => '{route.user}', 'ref' => '{query.ref}']),
    ]);

    Route::get('/profiles/{user}', fn () => Menu::build('header')->first()?->url ?? 'hidden');

    $this->get('/profiles/42?ref=newsletter')->assertSee(url('/users/42?ref=newsletter'), escape: false);
    $this->get('/profiles/42')->assertSee('hidden');
});

it('uses the route key of bound models', function (): void {
    Menu::sync('header', [routeItem('Profile', 'users.show', ['user' => '{route.member}'])]);

    $member = admin();
    Route::middleware('web')->get('/members/{member}', fn (User $member) => Menu::build('header')->first()?->url ?? 'hidden')
        ->whereNumber('member');
    Route::bind('member', fn (string $value): User => User::query()->findOrFail($value));

    $this->get('/members/'.$member->getKey())->assertSee(url('/users/'.$member->getKey()), escape: false);
});

it('supports registered URL parameters', function (): void {
    Menu::registerUrlParameter('team', fn (?Authenticatable $user, Request $request, ?string $path): ?string => $user === null ? null : ($path === 'slug' ? 'acme' : '7'));

    Menu::sync('header', [
        routeItem('Team', 'users.show', ['user' => '{team}']),
        linkItem('Team page', '/teams/{team.slug}'),
    ]);

    $this->actingAs(admin());

    expect(menuUrls())->toBe(['Team' => url('/users/7'), 'Team page' => '/teams/acme']);
});

it('leaves unknown placeholders in URLs as they are', function (): void {
    Menu::sync('header', [linkItem('Docs', '/docs/{version}')]);

    expect(menuUrls())->toBe(['Docs' => '/docs/{version}']);
});

it('validates route placeholders on save', function (): void {
    Menu::sync('header', [routeItem('Profile', 'users.show', ['user' => '{user}'])]);

    expect(fn () => Menu::sync('header', [routeItem('Profile', 'users.show', ['user' => '{nope}'])]))
        ->toThrow(InvalidMenuTree::class, '{nope}');
});

it('rejects invalid parameter names', function (): void {
    app(UrlParameters::class)->register('not valid', fn (): string => 'x');
})->throws(InvalidArgumentException::class);
