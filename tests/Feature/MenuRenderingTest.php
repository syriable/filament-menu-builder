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
    it('renders links with the Filament link component', function (): void {
        $link = one(renderMenu([resolved('About')]), '//a');

        expect($link->getAttribute('href'))->toBe('/about')
            ->and($link->getAttribute('class'))->toContain('fi-link')->toContain('mb-item-link')
            ->and(trim($link->textContent))->toBe('About');
    });

    it('renders headings as a semibold Filament link without a URL', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading])]);

        $heading = one($xpath, '//span[contains(@class, "mb-item-heading")]');

        expect($xpath->query('//a')->length)->toBe(0)
            ->and($heading->getAttribute('class'))->toContain('fi-link')->toContain('fi-font-semibold')
            ->and($heading->textContent)->toContain('Services');
    });

    it('renders headings with a configurable tag', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading])], 'heading-tag="strong"');

        expect(one($xpath, '//strong[contains(@class, "mb-item-heading")]'))->toBeInstanceOf(DOMElement::class);
    });

    it('falls back to a span for an invalid heading tag', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading])], 'heading-tag="h2 onclick"');

        expect(one($xpath, '//span[contains(@class, "mb-item-heading")]'))->toBeInstanceOf(DOMElement::class);
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
            ->and(one($xpath, '//a[@href="/home"]')->getAttribute('class'))->toContain('mb-active')->not->toContain('fi-color')
            ->and(one($xpath, '//a[@href="https://example.com"]')->getAttribute('target'))->toBe('_blank')
            ->and(one($xpath, '//a[@href="https://example.com"]')->getAttribute('rel'))->toBe('noopener noreferrer');
    });

    it('uses the item color', function (): void {
        expect(one(renderMenu([resolved('Sale', ['color' => 'danger'])]), '//a')->getAttribute('class'))->toContain('fi-color-danger');
    });

    it('renders known icons and skips unknown ones', function (): void {
        $xpath = renderMenu([
            resolved('Home', ['icon' => 'heroicon-o-home']),
            resolved('Broken', ['icon' => 'heroicon-o-does-not-exist']),
        ]);

        expect($xpath->query('//a[@href="/home"]//svg')->length)->toBe(1)
            ->and($xpath->query('//a[@href="/broken"]//svg')->length)->toBe(0);
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

    it('escapes attribute values exactly once', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [
            resolved('Evil', ['attributes' => ['title' => '"><script>alert(1)</script> & co', 'data-x' => "a' onmouseover='b"]]),
        ]]);

        expect($html)->not->toContain('<script>alert(1)</script>')
            ->not->toContain('&amp;quot;')
            ->toContain('title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt; &amp; co"')
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
            'url' => null,
            'renderAs' => RenderAs::Heading,
            'attributes' => ['data-section' => 'services'],
            'children' => [resolved('Design', ['depth' => 2])],
        ])]);

        $elements = $xpath->query('//*[@data-section]');

        expect($elements->length)->toBe(1)
            ->and($elements->item(0)->getAttribute('class'))->toContain('mb-trigger');
    });
});

describe('dropdowns', function (): void {
    it('renders root items and leaves without dropdowns', function (): void {
        $xpath = renderMenu([resolved('Home'), resolved('About')]);

        expect($xpath->query('//ul[contains(@class, "mb-root")]/li')->length)->toBe(2)
            ->and($xpath->query('//*[contains(@class, "fi-dropdown")]')->length)->toBe(0)
            ->and($xpath->query('//*[@data-mb-toggle]')->length)->toBe(0);
    });

    it('turns items with children into Filament dropdowns', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Web', ['depth' => 2]),
            resolved('Design', ['depth' => 2]),
        ]])]);

        $dropdown = one($xpath, '//div[contains(@class, "fi-dropdown ")]');
        $trigger = one($xpath, '//div[contains(@class, "fi-dropdown-trigger")]/button');

        expect($dropdown->getAttribute('x-data'))->toBe('filamentDropdown')
            ->and($dropdown->getAttribute('data-level'))->toBe('1')
            ->and($trigger->getAttribute('class'))->toContain('fi-link')->toContain('mb-trigger')
            ->and($xpath->query('.//svg[contains(@class, "mb-chevron")]', $trigger)->length)->toBe(1)
            ->and(one($xpath, '//div[contains(@class, "fi-dropdown-panel")]')->getAttribute('x-float.placement.bottom-start.flip.shift.offset'))->not->toBeNull()
            ->and($xpath->query('//div[contains(@class, "fi-dropdown-panel")]//a[contains(@class, "fi-dropdown-list-item")]')->length)->toBe(2);
    });

    it('lists the page of a parent link as the first entry', function (): void {
        $xpath = renderMenu([resolved('Services', ['isCurrent' => true, 'children' => [resolved('Web', ['depth' => 2])]])]);

        $links = $xpath->query('//div[contains(@class, "fi-dropdown-panel")]//a');

        expect($xpath->query('//div[contains(@class, "fi-dropdown-trigger")]/button')->length)->toBe(1)
            ->and($links->length)->toBe(2)
            ->and($links->item(0)->getAttribute('href'))->toBe('/services')
            ->and($links->item(0)->getAttribute('class'))->toContain('mb-parent-link')
            ->and($links->item(0)->getAttribute('aria-current'))->toBe('page')
            ->and($links->item(1)->getAttribute('href'))->toBe('/web');
    });

    it('renders headings in a panel as dropdown headers', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Group', ['depth' => 2, 'url' => null, 'renderAs' => RenderAs::Heading]),
        ]])]);

        expect(one($xpath, '//div[contains(@class, "fi-dropdown-header")]')->textContent)->toContain('Group');
    });

    it('renders button items in a panel as Filament buttons', function (): void {
        $xpath = renderMenu([resolved('Account', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Sign up', ['depth' => 2, 'type' => 'button', 'url' => '/register', 'renderAs' => RenderAs::Button, 'color' => 'success']),
        ]])]);

        expect(one($xpath, '//div[contains(@class, "mb-dropdown-entry")]/a[contains(@class, "fi-btn")]')->getAttribute('class'))
            ->toContain('fi-color-success');
    });

    it('supports arbitrarily nested dropdowns opening towards the inline end', function (string $direction, string $placement): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Development', ['depth' => 2, 'url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
                resolved('Laravel', ['depth' => 3]),
                resolved('Mobile', ['depth' => 3, 'url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
                    resolved('iOS', ['depth' => 4]),
                    resolved('Android', ['depth' => 4]),
                ]]),
            ]]),
        ]])], 'direction="'.$direction.'"');

        $nested = one($xpath, '//div[contains(@class, "mb-dropdown")][@data-level="3"]');

        expect($xpath->query('//div[contains(@class, "mb-dropdown")]')->length)->toBe(3)
            ->and(one($xpath, '//div[@data-level="2"]/div[contains(@class, "fi-dropdown-trigger")]/button')->getAttribute('class'))->toContain('fi-dropdown-list-item')
            ->and($xpath->query('div[contains(@class, "fi-dropdown-panel")]', $nested)->item(0)->hasAttribute("x-float.placement.{$placement}.flip.shift.offset"))->toBeTrue()
            ->and($xpath->query('.//a', $nested)->item(0)->getAttribute('href'))->toBe('/ios');
    })->with([
        'ltr' => ['ltr', 'right-start'],
        'rtl' => ['rtl', 'left-start'],
    ]);

    it('treats empty children as a leaf', function (): void {
        $xpath = renderMenu([resolved('Home', ['children' => []])]);

        expect($xpath->query('//*[contains(@class, "fi-dropdown")]')->length)->toBe(0);
    });
});

describe('tree', function (): void {
    it('renders accordions with a Filament icon button toggle', function (): void {
        $xpath = renderMenu([resolved('Services', ['children' => [resolved('Web', ['depth' => 2]), resolved('Design', ['depth' => 2])]])], 'variant="tree"');

        $entry = one($xpath, '//li[contains(@class, "mb-has-children")]');
        $toggle = one($xpath, '//button[@data-mb-toggle]');
        $submenu = one($xpath, '//ul[contains(@class, "mb-submenu")]');

        expect($toggle->getAttribute('class'))->toContain('fi-icon-btn')
            ->and($toggle->getAttribute('aria-label'))->toContain('Services')
            ->and($toggle->getAttribute('aria-expanded'))->toBe('false')
            ->and($toggle->getAttribute('aria-controls'))->toBe($submenu->getAttribute('id'))
            ->and($submenu->getAttribute('data-level'))->toBe('2')
            ->and($submenu->parentNode->isSameNode($entry))->toBeTrue()
            ->and($xpath->query('li', $submenu)->length)->toBe(2)
            ->and(one($xpath, '//a[@href="/services"]')->getAttribute('class'))->not->toContain('mb-trigger');
    });

    it('supports arbitrarily nested accordions', function (): void {
        $xpath = renderMenu([resolved('Services', ['children' => [
            resolved('Development', ['depth' => 2, 'children' => [
                resolved('Mobile', ['depth' => 3, 'children' => [resolved('iOS', ['depth' => 4]), resolved('Android', ['depth' => 4])]]),
            ]]),
        ]])], 'variant="tree"');

        expect($xpath->query('//*[@data-mb-toggle]')->length)->toBe(3)
            ->and($xpath->query('//ul[@data-level="4"]/li')->length)->toBe(2);
    });

    it('marks the active trail and opens it in tree menus', function (): void {
        $items = [resolved('Services', ['isActiveTrail' => true, 'children' => [resolved('Design', ['depth' => 2, 'isCurrent' => true])]])];

        $tree = renderMenu($items, 'variant="tree"');
        $dropdown = one(renderMenu($items), '//li[contains(@class, "mb-active-trail")]');

        expect(one($tree, '//li[contains(@class, "mb-active-trail")]')->hasAttribute('data-open'))->toBeTrue()
            ->and(one($tree, '//button[@data-mb-toggle]')->getAttribute('aria-expanded'))->toBe('true')
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
            ->and($xpath->query('//*[contains(@class, "fi-dropdown")]')->length)->toBe(0)
            ->and($xpath->query('//ul[contains(@class, "mb-submenu")]')->length)->toBe(2);
    });

    it('provides header and sidebar wrappers', function (): void {
        $items = [resolved('Home')];

        expect(one(render('<x-menu-builder::header :items="$items" label="Main" class="site-nav" />', compact('items')), '//nav')->getAttribute('class'))
            ->toContain('mb-menu--dropdown')->toContain('mb-menu--header')->toContain('site-nav')
            ->and(one(render('<x-menu-builder::header :items="$items" label="Main" />', compact('items')), '//nav')->getAttribute('aria-label'))->toBe('Main')
            ->and(one(render('<x-menu-builder::sidebar :items="$items" />', compact('items')), '//nav')->getAttribute('data-mb-menu'))->toBe('tree');
    });

    it('passes the direction through the wrappers', function (): void {
        $items = [resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Web', ['depth' => 2, 'url' => null, 'renderAs' => RenderAs::Heading, 'children' => [resolved('Laravel', ['depth' => 3])]]),
        ]])];

        $xpath = render('<x-menu-builder::header :items="$items" direction="rtl" />', compact('items'));

        expect(one($xpath, '//nav')->hasAttribute('direction'))->toBeFalse()
            ->and($xpath->query('//div[@x-float.placement.left-start.flip.shift.offset]')->length)->toBe(1);
    });

    it('renders items through a custom component', function (): void {
        view()->addNamespace('app', __DIR__.'/../Fixtures/views');

        $items = [resolved('Home'), resolved('Services', ['children' => [resolved('Design', ['depth' => 2])]])];

        $tree = renderMenu($items, 'variant="tree" item-component="app::menu-item"');
        $dropdown = renderMenu($items, 'item-component="app::menu-item"');

        expect($tree->query('//em[@class="custom-item"]')->length)->toBe(3)
            ->and(one($tree, '//em[@data-level="2"]')->textContent)->toBe('Design')
            ->and($tree->query('//*[@data-mb-toggle]')->length)->toBe(1)
            ->and($dropdown->query('//em[@class="custom-item"]')->length)->toBe(3)
            ->and(one($dropdown, '//div[contains(@class, "fi-dropdown-panel")]//em')->textContent)->toBe('Design');
    });

    it('prints the frontend assets once', function (): void {
        config()->set('menu-builder.frontend.assets', true);

        $html = Blade::render('<x-menu-builder::menu :items="$items" /><x-menu-builder::menu :items="$items" />', ['items' => [resolved('Home')]]);

        expect(substr_count($html, '<style'))->toBe(1)
            ->and(substr_count($html, '<script'))->toBe(1)
            ->and($html)->toContain('.mb-menu');
    });
});

describe('colors and classes', function (): void {
    it('lets links and headings inherit the color of the menu', function (): void {
        $xpath = renderMenu([
            resolved('Home', ['isCurrent' => true]),
            resolved('About'),
            resolved('Group', ['url' => null, 'renderAs' => RenderAs::Heading]),
        ]);

        expect($xpath->query('//*[contains(@class, "mb-item")][contains(@class, "fi-color")]')->length)->toBe(0);
    });

    it('keeps an explicit item color', function (): void {
        expect(one(renderMenu([resolved('Sale', ['color' => 'danger'])]), '//a')->getAttribute('class'))->toContain('fi-color-danger');
    });

    it('adds item, active and dropdown classes', function (string $template): void {
        $xpath = render($template, ['items' => [
            resolved('Home', ['isCurrent' => true]),
            resolved('Services', ['isActiveTrail' => true, 'url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
                resolved('Web', ['depth' => 2, 'isCurrent' => true]),
                resolved('Design', ['depth' => 2]),
            ]]),
            resolved('About'),
        ]]);

        expect($xpath->query('//*[contains(@class, "text-white")]')->length)->toBe(5)
            ->and($xpath->query('//*[contains(@class, "text-amber-300")]')->length)->toBe(3)
            ->and(one($xpath, '//a[@href="/about"]')->getAttribute('class'))->toContain('text-white')->not->toContain('text-amber-300')
            ->and(one($xpath, '//a[@href="/web"]')->getAttribute('class'))->toContain('text-amber-300')
            ->and(one($xpath, '//div[contains(@class, "mb-panel")]')->getAttribute('class'))->toContain('bg-gray-900');
    })->with([
        'menu' => '<x-menu-builder::menu :items="$items" item-class="text-white" active-class="text-amber-300" dropdown-class="bg-gray-900" />',
        'header' => '<x-menu-builder::header :items="$items" item-class="text-white" active-class="text-amber-300" dropdown-class="bg-gray-900" />',
    ]);

    it('adds the item classes in tree menus', function (): void {
        $xpath = render('<x-menu-builder::sidebar :items="$items" item-class="nav-link" />', ['items' => [
            resolved('Services', ['children' => [resolved('Web', ['depth' => 2])]]),
        ]]);

        expect($xpath->query('//a[contains(@class, "nav-link")]')->length)->toBe(2);
    });

    it('ships plain styles that Tailwind utilities override', function (): void {
        $css = Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css();

        expect($css)->toContain('@layer components')
            ->toContain('var(--mb-color, currentColor)')
            ->toContain('var(--mb-active-color')
            ->toContain('var(--mb-dropdown-color')
            ->toMatch('/\.fi-dropdown-list-item[^{]*:hover[^{]*\{\s*background-color: transparent;/')
            ->not->toContain('@layer utilities');
    });
});

describe('text style', function (): void {
    it('adds the text options to every kind of item element', function (): void {
        $style = Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::fromArray(['weight' => 'bold', 'underline' => 'none', 'hover_color' => '#f59e0b']);

        $xpath = renderMenu([
            resolved('About', ['textStyle' => $style]),
            resolved('Login', ['type' => 'button', 'url' => null, 'renderAs' => RenderAs::Button, 'textStyle' => $style]),
            resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'textStyle' => $style, 'children' => [
                resolved('Web', ['depth' => 2, 'textStyle' => $style]),
                resolved('Group', ['depth' => 2, 'url' => null, 'renderAs' => RenderAs::Heading, 'textStyle' => $style]),
            ]]),
        ]);

        $elements = $xpath->query('//*[contains(@class, "mb-weight-bold")]');

        expect($elements->length)->toBe(5);

        foreach ($elements as $element) {
            expect($element->getAttribute('class'))->toContain('mb-underline-none')->toContain('mb-hover-color')
                ->and($element->getAttribute('style'))->toContain('--mb-item-hover-color: #f59e0b');
        }
    });

    it('keeps the text options on the item when attributes target the wrapper', function (): void {
        $xpath = renderMenu([resolved('About', [
            'attributes' => ['data-x' => 'y'],
            'attributeTarget' => AttributeTarget::Wrapper,
            'textStyle' => Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::fromArray(['italic' => true]),
        ])]);

        expect(one($xpath, '//a')->getAttribute('class'))->toContain('mb-italic')
            ->and(one($xpath, '//li')->getAttribute('class'))->not->toContain('mb-italic');
    });

    it('merges the hover color with a style attribute', function (): void {
        $link = one(renderMenu([resolved('About', [
            'attributes' => ['style' => 'letter-spacing: 1px'],
            'textStyle' => Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::fromArray(['hover_color' => 'rgb(1 2 3)']),
        ])]), '//a');

        expect($link->getAttribute('style'))->toContain('letter-spacing: 1px')->toContain('--mb-item-hover-color: rgb(1 2 3)');
    });

    it('draws underlines on the label, which the decoration of the link cannot reach', function (): void {
        $css = Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css();

        // The label sits in an inline-flex box; a decoration set on the link is never painted on it.
        expect($css)->toMatch('/:where\(\.mb-menu \.mb-content\) \{\s*display: inline-flex;/')
            ->toContain('.mb-item.fi-link:is(:hover, :focus-visible) .mb-label')
            ->toContain('.mb-underline-hover:is(:hover, :focus-visible) .mb-label')
            ->toContain('.mb-underline-always .mb-label')
            ->toContain('.mb-underline-none .mb-label')
            ->and(preg_match_all('/([^{}]*)\{\s*text-decoration-line: underline;/', (string) preg_replace('#/\*.*?\*/#s', '', $css), $matches))->toBeGreaterThan(0);

        foreach ($matches[1] as $selectors) {
            foreach (preg_split('/,\s*\n/', $selectors) ?: [] as $selector) {
                expect(trim($selector))->toEndWith('.mb-label');
            }
        }
    });

    it('animates the chevron of open dropdowns', function (): void {
        $css = Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css();

        expect($css)->toContain('.mb-dropdown[data-level="1"] > .fi-dropdown-trigger [aria-expanded="true"] .mb-chevron')
            ->toContain('rotate: 180deg')
            ->toContain('prefers-reduced-motion');

        $xpath = renderMenu([resolved('Services', ['children' => [resolved('Web', ['depth' => 2, 'children' => [resolved('Laravel', ['depth' => 3])]])]])]);

        expect($xpath->query('//div[@data-level="1"]/div[contains(@class, "fi-dropdown-trigger")]//*[contains(@class, "mb-chevron")]')->length)->toBe(1)
            ->and($xpath->query('//div[@data-level="2"]/div[contains(@class, "fi-dropdown-trigger")]//*[contains(@class, "mb-chevron")]')->length)->toBe(1);
    });

    it('ships a rule for every text option', function (): void {
        $css = Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css();
        $classes = [
            ...array_map(fn (Filament\Support\Enums\FontWeight $weight): string => 'mb-weight-'.$weight->value, Filament\Support\Enums\FontWeight::cases()),
            ...array_map(fn (string $size): string => 'mb-text-'.$size, Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::SIZES),
            ...array_map(fn (string $value): string => 'mb-underline-'.$value, ['always', 'none']),
            ...array_map(fn (string $value): string => 'mb-transform-'.$value, Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::TRANSFORMS),
            ...array_map(fn (string $value): string => 'mb-cursor-'.$value, Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle::CURSORS),
            'mb-italic',
            'mb-hover-color',
        ];

        foreach ($classes as $class) {
            expect($css)->toContain('.'.$class);
        }
    });
});

describe('screen visibility', function (): void {
    it('adds the screen classes to the wrapper of every variant', function (string $variant): void {
        $screens = Syriable\Filament\Plugins\MenuBuilder\Support\ScreenVisibility::fromArray(['from' => 'md', 'until' => 'xl']);

        $xpath = renderMenu([resolved('Apps', ['screens' => $screens]), resolved('About')], 'variant="'.$variant.'"');

        $li = one($xpath, '//li[contains(@class, "mb-show-from-md")]');

        expect($li->getAttribute('class'))->toContain('mb-entry')->toContain('mb-hide-from-xl')
            ->and($xpath->query('.//a[@href="/apps"]', $li)->length)->toBe(1)
            ->and($xpath->query('//*[contains(@class, "mb-show-from") or contains(@class, "mb-hide-from")]')->length)->toBe(1);
    })->with(['dropdown', 'tree', 'columns']);

    it('wraps dropdown entries that have screen classes', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Apps', ['depth' => 2, 'screens' => Syriable\Filament\Plugins\MenuBuilder\Support\ScreenVisibility::fromArray(['until' => 'md'])]),
            resolved('About', ['depth' => 2]),
        ]])]);

        expect(one($xpath, '//div[contains(@class, "mb-panel-entry")]')->getAttribute('class'))->toContain('mb-hide-from-md')
            ->and(one($xpath, '//div[contains(@class, "mb-panel-entry")]/a')->getAttribute('href'))->toBe('/apps')
            ->and($xpath->query('//div[contains(@class, "mb-panel-entry")]')->length)->toBe(1);
    });

    it('applies wrapper attributes to dropdown entries', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Apps', ['depth' => 2, 'attributes' => ['data-section' => 'apps'], 'attributeTarget' => AttributeTarget::Wrapper]),
        ]])]);

        expect(one($xpath, '//div[@data-section="apps"]')->getAttribute('class'))->toContain('mb-panel-entry')
            ->and(one($xpath, '//a[@href="/apps"]')->hasAttribute('data-section'))->toBeFalse();
    });

    it('prints no empty class or style attributes', function (): void {
        $html = Blade::render('<x-menu-builder::menu :items="$items" />', ['items' => [
            resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [resolved('Web', ['depth' => 2])]]),
        ]]);

        expect($html)->not->toContain('style=";"')->not->toContain('mb-panel-entry');
    });

    it('ships a media query for every breakpoint', function (): void {
        $css = Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css();

        foreach (Syriable\Filament\Plugins\MenuBuilder\Enums\Breakpoint::cases() as $breakpoint) {
            $rem = rtrim(rtrim(number_format($breakpoint->pixels() / 16, 2, '.', ''), '0'), '.').'rem';

            expect($css)->toMatch('/@media \(width < '.preg_quote($rem, '/').'\) \{\s*\.mb-menu \.mb-show-from-'.$breakpoint->value.'\.mb-show-from-'.$breakpoint->value.' \{ display: none; \}/')
                ->toMatch('/@media \(width >= '.preg_quote($rem, '/').'\) \{\s*\.mb-menu \.mb-hide-from-'.$breakpoint->value.'\.mb-hide-from-'.$breakpoint->value.' \{ display: none; \}/');
        }
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
            ->and($link->getAttribute('target'))->toBe('_blank')
            ->and($link->getAttribute('rel'))->toBe('noopener noreferrer');
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
        'top uses the Filament badge' => [BadgePosition::Top, '//button//*[contains(@class, "fi-btn-badge-ctn")]'],
        'start' => [BadgePosition::Start, '//button//span[contains(@class, "mb-badge--start")]'],
        'end' => [BadgePosition::End, '//button//span[contains(@class, "mb-badge--end")]'],
    ]);
});

describe('badges', function (): void {
    it('renders inline badges with the Filament badge component', function (BadgePosition $position, string $query): void {
        $xpath = renderMenu([resolved('Services', ['badge' => 'NEW', 'badgeColor' => 'success', 'badgePosition' => $position])]);

        $badge = one($xpath, $query);

        expect($badge->getAttribute('class'))->toContain('fi-badge')->toContain('fi-color-success')->toContain('mb-badge--'.$position->value)
            ->and(trim($badge->textContent))->toBe('NEW');
    })->with([
        'start (before the label)' => [BadgePosition::Start, '//a//span[contains(@class, "mb-badge")][following-sibling::span[@class="mb-label"]]'],
        'end (after the label)' => [BadgePosition::End, '//a//span[contains(@class, "mb-badge")][preceding-sibling::span[@class="mb-label"]]'],
    ]);

    it('renders top badges with the Filament link badge', function (): void {
        $badge = one(renderMenu([resolved('Services', ['badge' => 'NEW', 'badgeColor' => 'success', 'badgePosition' => BadgePosition::Top])]), '//a/div[contains(@class, "fi-link-badge-ctn")]/span');

        expect($badge->getAttribute('class'))->toContain('fi-badge')->toContain('fi-color-success')
            ->and(trim($badge->textContent))->toBe('NEW');
    });

    it('renders badges of dropdown entries with the list item badge', function (): void {
        $xpath = renderMenu([resolved('Services', ['url' => null, 'renderAs' => RenderAs::Heading, 'children' => [
            resolved('Careers', ['depth' => 2, 'badge' => '3', 'badgePosition' => BadgePosition::End]),
        ]])]);

        expect(trim(one($xpath, '//a[contains(@class, "fi-dropdown-list-item")]/span[contains(@class, "fi-badge")]')->textContent))->toBe('3');
    });

    it('uses logical positions so the same markup works in LTR and RTL', function (string $direction): void {
        $html = Blade::render('<div dir="'.$direction.'"><x-menu-builder::menu :items="$items" /></div>', ['items' => [
            resolved('خدمات', ['badge' => 'جديد', 'badgePosition' => BadgePosition::Start]),
        ]]);

        expect($html)->toContain('mb-badge--start')
            ->and(Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css())
            ->toContain('padding-inline-start')
            ->not->toMatch('/(?<![-\w])(left|right)\s*:/');
    })->with(['ltr', 'rtl']);

    it('renders no badge when none is set', function (): void {
        expect(renderMenu([resolved('Home')])->query('//*[contains(@class, "fi-badge")]')->length)->toBe(0);
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

        $trigger = one($xpath, '//button[contains(@class, "nav-services")]');

        expect($trigger->getAttribute('class'))->toContain('mb-trigger')
            ->and(trim(one($xpath, '//button[contains(@class, "nav-services")]/div[contains(@class, "fi-link-badge-ctn")]')->textContent))->toBe('New')
            ->and($xpath->query('//a[contains(@class, "mb-parent-link")]/@href')->item(0)?->nodeValue)->toBe('/services')
            ->and(one($xpath, '//button[@data-modal="login"]')->getAttribute('type'))->toBe('button')
            ->and(one($xpath, '//div[@data-level="2"]//a[@href="/laravel"]')->getAttribute('class'))->toContain('fi-dropdown-list-item');
    });
});

it('scopes the editor stylesheet so it never styles frontend menus', function (): void {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(__DIR__.'/../../resources/dist/menu-builder.css'));

    preg_match_all('/([^{};]+)\{/', $css, $matches);

    $selectors = collect($matches[1])
        ->map(fn (string $selector): string => trim($selector))
        ->reject(fn (string $selector): bool => str_starts_with($selector, '@'))
        ->flatMap(fn (string $selector): array => array_map(trim(...), explode(',', $selector)));

    expect($selectors)->not->toBeEmpty()
        ->and($selectors->reject(fn (string $selector): bool => str_contains($selector, '.mb-editor'))->all())->toBe([]);
});
