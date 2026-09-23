<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Enums\AttributeTarget;
use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;

beforeEach(function (): void {
    registerTestPlacements();
    config()->set('menu-builder.frontend.assets', false);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function resolved(string $label, array $overrides = []): ResolvedMenuItem
{
    static $id = 0;

    return new ResolvedMenuItem(...[
        'id' => ++$id,
        'type' => 'link',
        'label' => $label,
        'url' => '/'.strtolower($label),
        'depth' => 1,
        ...$overrides,
    ]);
}

function render(string $template, array $data = []): DOMXPath
{
    $html = Blade::render($template, $data);
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function renderMenu(iterable $items, string $extra = ''): DOMXPath
{
    return render('<x-menu-builder::menu :items="$items" '.$extra.' />', ['items' => $items]);
}

function one(DOMXPath $xpath, string $query): DOMElement
{
    $nodes = $xpath->query($query);

    expect($nodes)->not->toBeFalse()->and($nodes->length)->toBe(1, "Expected exactly one match for {$query}");

    return $nodes->item(0);
}

describe('root elements', function (): void {
    it('renders links as anchors', function (): void {
        $link = one(renderMenu([resolved('About')]), '//a');

        expect($link->getAttribute('href'))->toBe('/about')
            ->and($link->getAttribute('class'))->toContain('mb-item-link')
            ->and(trim($link->textContent))->toBe('About');
    });

    it('renders headings as spans without a link', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading])]);

        expect($xpath->query('//a')->length)->toBe(0)
            ->and(one($xpath, '//span[contains(@class, "mb-item-heading")]')->textContent)->toContain('Services');
    });

    it('renders headings with a configurable tag', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading])], 'heading-tag="strong"');

        expect(one($xpath, '//strong[contains(@class, "mb-item-heading")]'))->toBeInstanceOf(DOMElement::class);
    });

    it('renders buttons without a URL', function (): void {
        $button = one(renderMenu([resolved('Login', ['url' => null, 'renderAs' => RenderAs::Button])]), '//button[contains(@class, "mb-item")]');

        expect($button->getAttribute('type'))->toBe('button')
            ->and($button->hasAttribute('href'))->toBeFalse()
            ->and(trim($button->textContent))->toBe('Login');
    });

    it('renders a link without URL as heading', function (): void {
        $xpath = renderMenu([resolved('Broken', ['url' => null, 'renderAs' => RenderAs::Link])]);

        expect($xpath->query('//a')->length)->toBe(0)
            ->and(one($xpath, '//span[contains(@class, "mb-item-heading")]'))->toBeInstanceOf(DOMElement::class);
    });

    it('marks the current page and new tabs', function (): void {
        $xpath = renderMenu([
            resolved('Home', ['isCurrent' => true]),
            resolved('Docs', ['url' => 'https://example.com', 'openInNewTab' => true]),
        ]);

        expect(one($xpath, '//a[@href="/home"]')->getAttribute('aria-current'))->toBe('page')
            ->and(one($xpath, '//a[@href="https://example.com"]')->getAttribute('target'))->toBe('_blank')
            ->and(one($xpath, '//a[@href="https://example.com"]')->getAttribute('rel'))->toBe('noopener noreferrer');
    });
});

describe('attributes', function (): void {
    it('applies arbitrary attributes to the root element', function (): void {
        $button = one(renderMenu([resolved('Login', [
            'url' => null,
            'renderAs' => RenderAs::Button,
            'attributes' => [
                'class' => 'btn btn-primary',
                'id' => 'open-login',
                'data-modal' => 'login',
                'aria-label' => 'Open login',
                'x-on:click' => "\$dispatch('open-modal', { id: 'login' })",
                '@click.prevent' => 'open = true',
                ':class' => "{ 'active': open }",
                'wire:click' => 'openLogin',
            ],
        ])]), '//button[@id="open-login"]');

        expect($button->getAttribute('class'))->toContain('fi-btn')->toContain('mb-item-button')->toContain('btn btn-primary')
            ->and($button->getAttribute('data-modal'))->toBe('login')
            ->and($button->getAttribute('aria-label'))->toBe('Open login')
            ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('open-modal', { id: 'login' })")
            ->and($button->getAttribute(':class'))->toBe("{ 'active': open }")
            ->and($button->getAttribute('wire:click'))->toBe('openLogin')
            ->and($button->getAttribute('type'))->toBe('button');
    });

    it('keeps Alpine shorthand attributes', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [
            resolved('Menu', ['url' => null, 'renderAs' => RenderAs::Button, 'attributes' => ['@click.prevent' => 'open = ! open']]),
        ]]);

        expect($html)->toContain('@click.prevent="open = ! open"');
    });

    it('lets attributes override the defaults', function (): void {
        $xpath = renderMenu([
            resolved('Send', ['url' => null, 'renderAs' => RenderAs::Button, 'attributes' => ['type' => 'submit']]),
            resolved('Out', ['url' => 'https://example.com', 'openInNewTab' => true, 'attributes' => ['rel' => 'external']]),
        ]);

        expect(one($xpath, '//button[contains(@class, "mb-item")]')->getAttribute('type'))->toBe('submit')
            ->and(one($xpath, '//a')->getAttribute('rel'))->toBe('external');
    });

    it('escapes attribute values', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [
            resolved('Evil', ['attributes' => ['title' => '"><script>alert(1)</script>', 'data-x' => "a' onmouseover='b"]]),
        ]]);

        expect($html)->not->toContain('<script>alert(1)</script>')
            ->toContain('title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
            ->toContain('data-x="a&#039; onmouseover=&#039;b"');
    });

    it('never renders malformed attribute names', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [
            resolved('Evil', ['attributes' => Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes::normalize([
                'onclick"><script>' => 'x',
                'data ok' => 'x',
                'a=b' => 'x',
                'data-fine' => 'yes',
            ])]),
        ]]);

        expect($html)->not->toContain('<script>')
            ->not->toContain('data ok')
            ->toContain('data-fine="yes"');
    });

    it('renders boolean attributes correctly', function (): void {
        $button = one(renderMenu([resolved('Soon', [
            'url' => null,
            'renderAs' => RenderAs::Button,
            'attributes' => Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes::normalize([
                'disabled' => '',
                'hidden' => 'false',
                'required' => '0',
                'aria-expanded' => 'false',
                'data-flag' => '',
            ]),
        ])]), '//button[contains(@class, "mb-item")]');

        expect($button->hasAttribute('disabled'))->toBeTrue()
            ->and($button->hasAttribute('hidden'))->toBeFalse()
            ->and($button->hasAttribute('required'))->toBeFalse()
            ->and($button->getAttribute('aria-expanded'))->toBe('false')
            ->and($button->hasAttribute('data-flag'))->toBeTrue();
    });

    it('applies attributes to the wrapper when targeted', function (): void {
        $xpath = renderMenu([resolved('Services', [
            'attributes' => ['class' => 'mega', 'data-section' => 'services'],
            'attributeTarget' => AttributeTarget::Wrapper,
        ])]);

        $li = one($xpath, '//li[@data-section="services"]');

        expect($li->getAttribute('class'))->toContain('mb-entry')->toContain('mega')
            ->and(one($xpath, '//a')->hasAttribute('data-section'))->toBeFalse();
    });

    it('does not move item attributes to the wrapper or children', function (): void {
        $xpath = renderMenu([resolved('Services', [
            'attributes' => ['data-section' => 'services'],
            'children' => [resolved('Design', ['depth' => 2])],
        ])]);

        $elements = $xpath->query('//*[@data-section]');

        expect($elements->length)->toBe(1)
            ->and($elements->item(0)->nodeName)->toBe('a')
            ->and($elements->item(0)->getAttribute('href'))->toBe('/services');
    });
});

describe('tree', function (): void {
    it('renders root items and leaves without dropdown controls', function (): void {
        $xpath = renderMenu([resolved('Home'), resolved('About')]);

        expect($xpath->query('//ul[contains(@class, "mb-root")]/li')->length)->toBe(2)
            ->and($xpath->query('//*[@data-mb-toggle]')->length)->toBe(0)
            ->and($xpath->query('//ul[contains(@class, "mb-submenu")]')->length)->toBe(0);
    });

    it('turns items with children into dropdowns', function (): void {
        $xpath = renderMenu([resolved('Services', ['children' => [resolved('Web', ['depth' => 2]), resolved('Design', ['depth' => 2])]])]);

        $entry = one($xpath, '//li[contains(@class, "mb-has-children")]');
        $toggle = one($xpath, '//button[@data-mb-toggle]');
        $submenu = one($xpath, '//ul[contains(@class, "mb-submenu")]');

        expect($toggle->getAttribute('aria-expanded'))->toBe('false')
            ->and($toggle->getAttribute('aria-controls'))->toBe($submenu->getAttribute('id'))
            ->and($submenu->getAttribute('data-level'))->toBe('2')
            ->and($submenu->parentNode->isSameNode($entry))->toBeTrue()
            ->and($xpath->query('li', $submenu)->length)->toBe(2);
    });

    it('supports arbitrarily nested dropdowns', function (): void {
        $xpath = renderMenu([resolved('Services', ['children' => [
            resolved('Development', ['depth' => 2, 'children' => [
                resolved('Laravel', ['depth' => 3]),
                resolved('Mobile', ['depth' => 3, 'children' => [resolved('iOS', ['depth' => 4]), resolved('Android', ['depth' => 4])]]),
            ]]),
            resolved('Design', ['depth' => 2]),
        ]])]);

        expect($xpath->query('//li[contains(@class, "mb-has-children")]')->length)->toBe(3)
            ->and($xpath->query('//*[@data-mb-toggle]')->length)->toBe(3)
            ->and($xpath->query('//ul[@data-level="4"]/li')->length)->toBe(2)
            ->and(one($xpath, '//ul[@data-level="4"]/li[1]//a')->getAttribute('href'))->toBe('/ios');
    });

    it('treats empty children as a leaf', function (): void {
        $xpath = renderMenu([resolved('Home', ['children' => []])]);

        expect($xpath->query('//*[@data-mb-toggle]')->length)->toBe(0);
    });

    it('marks the active trail and opens it in tree menus', function (): void {
        $items = [resolved('Services', ['isActiveTrail' => true, 'children' => [resolved('Design', ['depth' => 2, 'isCurrent' => true])]])];

        $tree = one(renderMenu($items, 'variant="tree"'), '//li[contains(@class, "mb-active-trail")]');
        $dropdown = one(renderMenu($items), '//li[contains(@class, "mb-active-trail")]');

        expect($tree->hasAttribute('data-open'))->toBeTrue()
            ->and($dropdown->hasAttribute('data-open'))->toBeFalse();
    });

    it('renders footer columns without toggles', function (): void {
        $xpath = render('<x-menu-builder::footer :items="$items" />', ['items' => [
            resolved('Company', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [resolved('About', ['depth' => 2])]]),
            resolved('Legal', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [resolved('Privacy', ['depth' => 2])]]),
        ]]);

        $nav = one($xpath, '//nav');

        expect($nav->getAttribute('class'))->toContain('mb-menu--columns')->toContain('mb-menu--footer')
            ->and($nav->getAttribute('data-mb-menu'))->toBe('columns')
            ->and($xpath->query('//*[@data-mb-toggle]')->length)->toBe(0)
            ->and($xpath->query('//ul[contains(@class, "mb-submenu")]')->length)->toBe(2);
    });

    it('provides header and sidebar wrappers', function (): void {
        $items = [resolved('Home')];

        expect(one(render('<x-menu-builder::header :items="$items" label="Main" class="site-nav" />', compact('items')), '//nav')->getAttribute('class'))
            ->toContain('mb-menu--dropdown')->toContain('mb-menu--header')->toContain('site-nav')
            ->and(one(render('<x-menu-builder::header :items="$items" label="Main" />', compact('items')), '//nav')->getAttribute('aria-label'))->toBe('Main')
            ->and(one(render('<x-menu-builder::sidebar :items="$items" />', compact('items')), '//nav')->getAttribute('data-mb-menu'))->toBe('tree');
    });

    it('renders items through a custom component', function (): void {
        view()->addNamespace('app', __DIR__.'/../Fixtures/views');

        $xpath = renderMenu([resolved('Home'), resolved('Services', ['children' => [resolved('Design', ['depth' => 2])]])], 'item-component="app::menu-item"');

        expect($xpath->query('//em[@class="custom-item"]')->length)->toBe(3)
            ->and(one($xpath, '//em[@data-level="2"]')->textContent)->toBe('Design')
            ->and($xpath->query('//*[@data-mb-toggle]')->length)->toBe(1);
    });

    it('prints the frontend assets once', function (): void {
        config()->set('menu-builder.frontend.assets', true);

        $html = Blade::render('<x-menu-builder::menu :items="$items" /><x-menu-builder::menu :items="$items" />', ['items' => [resolved('Home')]]);

        expect(substr_count($html, '<style'))->toBe(1)
            ->and(substr_count($html, '<script'))->toBe(1)
            ->and($html)->toContain('.mb-menu');
    });
});

describe('filament buttons', function (): void {
    it('renders button items with the Filament button component', function (): void {
        $button = one(renderMenu([resolved('Login', [
            'type' => 'button',
            'url' => null,
            'renderAs' => RenderAs::Button,
            'color' => 'danger',
            'data' => ['size' => 'lg', 'outlined' => true],
            'attributes' => ['data-modal' => 'login'],
        ])]), '//button[contains(@class, "fi-btn")]');

        expect($button->getAttribute('class'))
            ->toContain('fi-color-danger')
            ->toContain('fi-size-lg')
            ->toContain('fi-outlined')
            ->toContain('mb-item-button')
            ->and($button->getAttribute('type'))->toBe('button')
            ->and($button->getAttribute('data-modal'))->toBe('login')
            ->and(trim($button->textContent))->toBe('Login');
    });

    it('uses sensible defaults', function (): void {
        $button = one(renderMenu([resolved('Go', ['type' => 'button', 'url' => null, 'renderAs' => RenderAs::Button])]), '//button[contains(@class, "fi-btn")]');

        expect($button->getAttribute('class'))->toContain('fi-color-primary')->toContain('fi-size-md')->not->toContain('fi-outlined');
    });

    it('renders a button with a URL as a link styled as a button', function (): void {
        $link = one(renderMenu([resolved('Sign up', [
            'type' => 'button',
            'url' => '/register',
            'openInNewTab' => true,
            'renderAs' => RenderAs::Button,
        ])]), '//a[contains(@class, "fi-btn")]');

        expect($link->getAttribute('href'))->toBe('/register')
            ->and($link->getAttribute('target'))->toBe('_blank');
    });

    it('escapes attribute values exactly once', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [resolved('X', [
            'type' => 'button',
            'url' => null,
            'renderAs' => RenderAs::Button,
            'attributes' => ['title' => '"><b>x & y'],
        ])]]);

        expect($html)->toContain('title="&quot;&gt;&lt;b&gt;x &amp; y"')
            ->not->toContain('&amp;quot;')
            ->not->toContain('<b>x');
    });

    it('renders the badge on the button', function (BadgePosition $position, string $query): void {
        $xpath = renderMenu([resolved('Cart', [
            'type' => 'button',
            'url' => null,
            'renderAs' => RenderAs::Button,
            'badge' => '3',
            'badgePosition' => $position,
        ])]);

        expect(trim(one($xpath, $query)->textContent))->toBe('3');
    })->with([
        'top uses the Filament badge' => [BadgePosition::Top, '//button//*[contains(@class, "fi-badge")]'],
        'start' => [BadgePosition::Start, '//button/span[contains(@class, "mb-badge--start")]'],
        'end' => [BadgePosition::End, '//button/span[contains(@class, "mb-badge--end")]'],
    ]);
});

describe('badges', function (): void {
    it('renders badges at the start, end or top', function (BadgePosition $position, string $query): void {
        $xpath = renderMenu([resolved('Services', ['badge' => 'NEW', 'badgeColor' => 'success', 'badgePosition' => $position])]);

        $badge = one($xpath, $query);

        expect($badge->getAttribute('class'))->toContain('mb-badge--'.$position->value)
            ->and($badge->getAttribute('data-color'))->toBe('success')
            ->and(trim($badge->textContent))->toBe('NEW');
    })->with([
        'start (before the label)' => [BadgePosition::Start, '//a/span[contains(@class, "mb-badge")][following-sibling::span[@class="mb-label"]]'],
        'end (after the label)' => [BadgePosition::End, '//a/span[contains(@class, "mb-badge")][preceding-sibling::span[@class="mb-label"]]'],
        'top (inside the label)' => [BadgePosition::Top, '//a/span[@class="mb-label"]/span[contains(@class, "mb-badge")]'],
    ]);

    it('uses logical positions so the same markup works in LTR and RTL', function (string $direction): void {
        $html = Blade::render('<div dir="'.$direction.'"><x-menu-builder::menu :items="$items" /></div>', ['items' => [
            resolved('خدمات', ['badge' => 'جديد', 'badgePosition' => BadgePosition::Start]),
        ]]);

        expect($html)->toContain('mb-badge--start')
            ->and(Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css())
            ->toContain('inset-inline-end')
            ->not->toMatch('/(?<![-\w])(left|right)\s*:/');
    })->with(['ltr', 'rtl']);

    it('renders no badge when none is set', function (): void {
        expect(renderMenu([resolved('Home')])->query('//*[contains(@class, "mb-badge")]')->length)->toBe(0);
    });
});

describe('with the menu builder', function (): void {
    it('renders a published menu end to end', function (): void {
        Menu::sync('header', [
            linkItem('Home', '/'),
            linkItem('Services', '/services', [
                'badge' => 'New',
                'data' => ['link_type' => 'url', 'url' => '/services', 'badge_position' => 'top', 'attributes' => ['class' => 'nav-services']],
                'children' => [linkItem('Web', '/web', ['children' => [linkItem('Laravel', '/laravel')]])],
            ]),
            headingItem('Login', ['data' => ['render_as' => 'button', 'attributes' => ['data-modal' => 'login']]]),
        ]);

        $xpath = renderMenu(Menu::build('header'));

        expect(one($xpath, '//a[@href="/services"]')->getAttribute('class'))->toContain('nav-services')
            ->and(one($xpath, '//a[@href="/services"]/span[@class="mb-label"]/span[contains(@class, "mb-badge--top")]')->textContent)->toBe('New')
            ->and(one($xpath, '//button[@data-modal="login"]')->getAttribute('type'))->toBe('button')
            ->and(one($xpath, '//ul[@data-level="3"]//a')->getAttribute('href'))->toBe('/laravel');
    });
});
