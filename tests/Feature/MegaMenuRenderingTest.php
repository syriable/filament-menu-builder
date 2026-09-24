<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;

/**
 * @param  list<ResolvedMenuItem>  $children
 * @param  array<string, mixed>  $extra
 */
function megaRenderItem(int $id, string $type, string $label, ?string $url, int $depth, array $children = [], array $extra = []): ResolvedMenuItem
{
    return new ResolvedMenuItem(...[
        'id' => $id,
        'type' => $type,
        'label' => $label,
        'url' => $url,
        'depth' => $depth,
        'children' => $children,
        'renderAs' => $url === null ? RenderAs::Heading : RenderAs::Link,
        ...$extra,
    ]);
}

/**
 * @return list<ResolvedMenuItem>
 */
function megaRenderMenu(): array
{
    return [
        megaRenderItem(1, 'mega-category', 'Design', '/design', 1, [
            megaRenderItem(11, 'heading', 'Logo & Brand', null, 2, [
                megaRenderItem(111, 'link', 'Logo Design', '/design/logo', 3, extra: ['badge' => 'New']),
            ]),
            megaRenderItem(12, 'link', 'Web Design', '/design/web', 2, [
                megaRenderItem(121, 'link', 'Landing Pages', '/design/web/landing', 3),
            ]),
        ], ['data' => ['columns' => 3]]),
        megaRenderItem(2, 'mega-category', 'Photography', '/photography', 1),
    ];
}

/**
 * @param  array<string, mixed>  $data
 */
function megaRender(string $template, array $data = []): string
{
    return Blade::render($template, ['items' => megaRenderMenu(), ...$data]);
}

describe('mega variant rendering', function (): void {
    it('renders a nav that loads the mega Alpine component', function (): void {
        $html = megaRender('<x-menu-builder::menu :items="$items" variant="mega" label="Categories" />');

        expect($html)
            ->toContain('mb-menu--mega')
            ->toContain('data-mb-menu="mega"')
            ->toContain('aria-label="Categories"')
            ->toContain('x-load-src=')
            ->toContain('menu-builder-mega')
            ->toContain('menuBuilderMega(')
            ->toContain('data-mb-mega-strip')
            ->toContain('data-mb-mega-scroll="start"')
            ->toContain('data-mb-mega-scroll="end"');
    });

    it('renders a panel with its column count for categories with children', function (): void {
        $html = megaRender('<x-menu-builder::mega :items="$items" />');

        expect($html)
            ->toContain('data-mb-mega-entry="1"')
            ->toContain('data-mb-mega-columns="3"')
            ->toContain('Logo &amp; Brand')
            ->toContain('href="/design/web"')
            ->toContain('href="/design/web/landing"')
            ->toContain('New');

        expect(substr_count($html, 'data-mb-mega-columns='))->toBe(1)
            ->and(substr_count($html, 'data-mb-mega-panel-id='))->toBe(1);
    });

    it('links the category page from its panel for touch visitors', function (): void {
        $html = megaRender('<x-menu-builder::mega :items="$items" />');

        expect($html)->toContain('mb-mega-view-all')
            ->toContain(__('menu-builder::menu-builder.frontend.view_all', ['item' => 'Design']));
    });

    it('passes the configured delays and breakpoint to the script', function (): void {
        config()->set('menu-builder.mega.breakpoint', 1024);

        $html = megaRender('<x-menu-builder::mega :items="$items" :open-delay="250" />');

        expect($html)->toContain('openDelay')
            ->toContain('250')
            ->toContain('1024');
    });

    it('sets the direction on the nav', function (string $direction): void {
        $html = megaRender('<x-menu-builder::mega :items="$items" :direction="$direction" />', ['direction' => $direction]);

        expect($html)->toContain('dir="'.$direction.'"');
    })->with(['ltr', 'rtl']);

    it('applies item-class inside the panels and panel-class to the panels', function (): void {
        $html = megaRender('<x-menu-builder::mega :items="$items" item-class="nav-item" panel-class="shadow-xl" />');

        expect(substr_count($html, 'nav-item'))->toBeGreaterThanOrEqual(6)
            ->and($html)->toContain('shadow-xl');
    });

    it('prints the mega styles once per page and only for the mega variant', function (): void {
        $mega = megaRender('<x-menu-builder::mega :items="$items" /><x-menu-builder::mega :items="$items" />');

        expect(substr_count($mega, '--mb-mega-column-width:'))->toBe(1);
    });

    it('leaves the other variants unchanged', function (string $variant): void {
        $html = megaRender('<x-menu-builder::menu :items="$items" :variant="$variant" />', ['variant' => $variant]);

        expect($html)->not->toContain('mb-mega-')
            ->not->toContain('menuBuilderMega');
    })->with(['dropdown', 'tree', 'columns']);
});
