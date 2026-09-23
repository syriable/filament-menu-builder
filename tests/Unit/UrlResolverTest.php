<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver;

beforeEach(function (): void {
    $this->resolver = app(UrlResolver::class);
});

it('accepts any kind of url as entered', function (string $url): void {
    expect($this->resolver->resolve(['link_type' => 'url', 'url' => $url]))->toBe($url);
})->with([
    '/pricing',
    '/about',
    'https://example.com',
    '#pricing',
    'mailto:test@example.com',
    'tel:+46123456789',
]);

it('treats empty urls as missing', function (): void {
    expect($this->resolver->resolve(['link_type' => 'url', 'url' => '  ']))->toBeNull()
        ->and($this->resolver->resolve([]))->toBeNull();
});

it('resolves named routes', function (): void {
    expect($this->resolver->resolve(['link_type' => 'route', 'route' => 'about']))->toBe('http://localhost/about');
});

it('resolves named routes with parameters', function (): void {
    expect($this->resolver->resolve([
        'link_type' => 'route',
        'route' => 'users.show',
        'route_parameters' => ['user' => 123],
    ]))->toBe('http://localhost/users/123');
});

it('passes additional parameters as query string', function (): void {
    expect($this->resolver->resolve([
        'link_type' => 'route',
        'route' => 'about',
        'route_parameters' => ['ref' => 'menu', 'empty' => ''],
    ]))->toBe('http://localhost/about?ref=menu');
});

it('returns null for missing routes and missing parameters', function (array $data): void {
    expect($this->resolver->resolve($data))->toBeNull();
})->with([
    'unknown route' => [['link_type' => 'route', 'route' => 'does-not-exist']],
    'no route' => [['link_type' => 'route']],
    'missing parameter' => [['link_type' => 'route', 'route' => 'users.show']],
]);

it('lists selectable GET routes', function (): void {
    $routes = $this->resolver->selectableRoutes(['users.*']);

    expect($routes)->toHaveKeys(['about', 'home'])
        ->not->toHaveKey('users.show')
        ->not->toHaveKey('contact.store')
        ->and($this->resolver->routeParameterNames('users.show'))->toBe(['user']);
});
