<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Enums\HttpMethod;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;

beforeEach(function (): void {
    registerTestPlacements();
    config()->set('menu-builder.frontend.assets', false);
    Route::post('/logout', fn () => 'bye')->name('logout');
    Route::delete('/account', fn () => 'gone')->name('account.destroy');
});

function methodItem(string $label, HttpMethod $method, array $overrides = []): ResolvedMenuItem
{
    static $id = 1000;

    return new ResolvedMenuItem(...[
        'id' => ++$id,
        'type' => 'link',
        'label' => $label,
        'url' => '/'.strtolower($label),
        'depth' => 1,
        'method' => $method,
        ...$overrides,
    ]);
}

function renderMethods(array $items): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div>'.Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => $items]).'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    return new DOMXPath($document);
}

it('renders GET links as plain links', function (): void {
    $xpath = renderMethods([methodItem('About', HttpMethod::Get)]);

    expect($xpath->query('//form')->length)->toBe(0)
        ->and($xpath->query('//a[@href="/about"]')->length)->toBe(1);
});

it('renders POST links as a submit button in a form', function (): void {
    $xpath = renderMethods([methodItem('Logout', HttpMethod::Post)]);

    $form = $xpath->query('//form[contains(@class, "mb-form")]')->item(0);
    $button = $xpath->query('button', $form)->item(0);

    expect($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe('/logout')
        ->and($xpath->query('input[@name="_token"]', $form)->length)->toBe(1)
        ->and($xpath->query('input[@name="_method"]', $form)->length)->toBe(0)
        ->and($button->getAttribute('type'))->toBe('submit')
        ->and($button->getAttribute('class'))->toContain('fi-link')->toContain('mb-item-link')
        ->and($button->hasAttribute('href'))->toBeFalse()
        ->and($xpath->query('//a')->length)->toBe(0);
});

it('spoofs PUT, PATCH and DELETE', function (HttpMethod $method): void {
    $xpath = renderMethods([methodItem('Account', $method)]);

    expect($xpath->query('//form[@method="POST"]/input[@name="_method"]')->item(0)?->getAttribute('value'))->toBe($method->value);
})->with([HttpMethod::Put, HttpMethod::Patch, HttpMethod::Delete]);

it('renders button items and dropdown entries as submit buttons', function (): void {
    $xpath = renderMethods([
        methodItem('Logout', HttpMethod::Post, ['type' => 'button', 'renderAs' => RenderAs::Button]),
        methodItem('Account', HttpMethod::Get, ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            methodItem('Delete', HttpMethod::Delete, ['depth' => 2]),
        ]]),
    ]);

    expect($xpath->query('//form[@action="/logout"]/button[contains(@class, "fi-btn")][@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//div[contains(@class, "fi-dropdown-panel")]//form[@action="/delete"]/button[contains(@class, "fi-dropdown-list-item")][@type="submit"]')->length)->toBe(1);
});

it('opens forms in a new tab when asked', function (): void {
    $xpath = renderMethods([methodItem('Export', HttpMethod::Post, ['openInNewTab' => true])]);

    expect($xpath->query('//form[@target="_blank"]')->length)->toBe(1)
        ->and($xpath->query('//button[@rel]')->length)->toBe(0);
});

it('does not list a form parent as the first dropdown entry', function (): void {
    $xpath = renderMethods([methodItem('Logout', HttpMethod::Post, ['children' => [methodItem('Everywhere', HttpMethod::Post, ['depth' => 2])]])]);

    expect($xpath->query('//*[contains(@class, "mb-parent-link")]')->length)->toBe(0);
});

it('resolves the method and never marks actions as current', function (): void {
    Menu::sync('header', [
        ['type' => 'link', 'label' => 'Logout', 'data' => ['link_type' => 'route', 'route' => 'logout', 'method' => 'POST']],
        ['type' => 'link', 'label' => 'Home', 'data' => ['link_type' => 'url', 'url' => '/']],
    ]);

    [$logout, $home] = Menu::build('header', currentUrl: url('/logout'))->all();

    expect($logout->method)->toBe(HttpMethod::Post)
        ->and($logout->usesForm())->toBeTrue()
        ->and($logout->isCurrent)->toBeFalse()
        ->and($logout->toArray()['method'])->toBe('POST')
        ->and($home->method)->toBe(HttpMethod::Get)
        ->and($home->usesForm())->toBeFalse();
});

it('validates that the route accepts the method', function (): void {
    Menu::sync('header', [['type' => 'link', 'label' => 'Delete', 'data' => ['link_type' => 'route', 'route' => 'account.destroy', 'method' => 'DELETE']]]);

    expect(fn () => Menu::sync('header', [['type' => 'link', 'label' => 'Logout', 'data' => ['link_type' => 'route', 'route' => 'logout']]]))
        ->toThrow(InvalidMenuTree::class, 'does not accept GET')
        ->and(fn () => Menu::sync('header', [['type' => 'link', 'label' => 'x', 'data' => ['link_type' => 'url', 'url' => '/x', 'method' => 'TRACE']]]))
        ->toThrow(InvalidMenuTree::class);
});

it('offers the routes of the chosen method', function (): void {
    $resolver = app(Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver::class);

    expect(array_keys($resolver->selectableRoutes([], HttpMethod::Post)))->toContain('logout')->not->toContain('about')
        ->and(array_keys($resolver->selectableRoutes()))->toContain('about')->not->toContain('logout')
        ->and(array_keys($resolver->selectableRoutes([], HttpMethod::Delete)))->toBe(['account.destroy']);
});
