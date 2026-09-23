<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\Filament\Plugins\MenuBuilder\Actions\CreateMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Actions\DeleteMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Actions\MoveMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Actions\ReorderMenuItems;
use Syriable\Filament\Plugins\MenuBuilder\Actions\UpdateMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Events\MenuPublished;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\StaleMenu;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

beforeEach(function (): void {
    registerTestPlacements();
});

/**
 * @return list<array{0: string|null, 1: string|null, 2: int}>
 */
function persisted(string $placement): array
{
    $items = MenuItem::query()->where('placement', $placement)->get()->keyBy('id');

    return $items
        ->sortBy(fn (MenuItem $item): array => [$item->parent_id ?? 0, $item->sort_order])
        ->map(fn (MenuItem $item): array => [$item->label, $items->get($item->parent_id)?->label, $item->sort_order])
        ->values()
        ->all();
}

it('creates items', function (): void {
    $create = app(CreateMenuItem::class);

    $services = $create->handle('header', linkItem('Services', '/services'));
    $create->handle('header', linkItem('Design', '/design'), $services);
    $create->handle('header', linkItem('Development', '/development'), $services->id);
    $create->handle('header', linkItem('Home', '/'), position: 0);

    expect($services)->toBeInstanceOf(MenuItem::class)
        ->and($services->data)->toBe(['link_type' => 'url', 'url' => '/services'])
        ->and(persisted('header'))->toBe([
            ['Home', null, 0],
            ['Services', null, 1],
            ['Design', 'Services', 0],
            ['Development', 'Services', 1],
        ]);
});

it('rejects invalid items', function (): void {
    app(CreateMenuItem::class)->handle('footer', linkItem('Not allowed at the root'));
})->throws(InvalidMenuTree::class);

it('rejects parents from another placement', function (): void {
    $heading = app(CreateMenuItem::class)->handle('footer', headingItem('Company'));

    app(CreateMenuItem::class)->handle('header', linkItem('About'), $heading);
})->throws(InvalidMenuTree::class, 'The parent item does not exist in this menu.');

it('updates items', function (): void {
    $item = app(CreateMenuItem::class)->handle('header', linkItem('Old', '/old'));

    $updated = app(UpdateMenuItem::class)->handle($item, [
        'label' => 'New',
        'data' => ['link_type' => 'route', 'route' => 'about'],
        'badge' => 'Hot',
        'is_active' => false,
    ]);

    expect($updated->label)->toBe('New')
        ->and($updated->data)->toBe(['link_type' => 'route', 'route' => 'about'])
        ->and($updated->badge)->toBe('Hot')
        ->and($updated->is_active)->toBeFalse();
});

it('does not persist invalid updates', function (): void {
    $item = app(CreateMenuItem::class)->handle('header', linkItem('Home', '/'));

    expect(fn () => app(UpdateMenuItem::class)->handle($item, ['data' => ['link_type' => 'route', 'route' => 'missing']]))
        ->toThrow(InvalidMenuTree::class)
        ->and($item->fresh()?->data)->toBe(['link_type' => 'url', 'url' => '/']);
});

it('reorders siblings', function (): void {
    $create = app(CreateMenuItem::class);
    $a = $create->handle('header', linkItem('A'));
    $b = $create->handle('header', linkItem('B'));
    $c = $create->handle('header', linkItem('C'));

    app(ReorderMenuItems::class)->handle('header', null, [$c->id, $a->id, $b->id]);

    expect(persisted('header'))->toBe([['C', null, 0], ['A', null, 1], ['B', null, 2]]);
});

it('moves items between parents and back to the root', function (): void {
    $create = app(CreateMenuItem::class);
    $services = $create->handle('header', linkItem('Services'));
    $design = $create->handle('header', linkItem('Design'), $services);
    $development = $create->handle('header', linkItem('Development'), $services);

    app(MoveMenuItem::class)->handle($development, $design);

    expect(persisted('header'))->toBe([
        ['Services', null, 0],
        ['Design', 'Services', 0],
        ['Development', 'Design', 0],
    ]);

    app(MoveMenuItem::class)->handle($development, null);

    expect(persisted('header'))->toBe([
        ['Services', null, 0],
        ['Development', null, 1],
        ['Design', 'Services', 0],
    ]);
});

it('prevents moving an item below its own descendant', function (): void {
    $create = app(CreateMenuItem::class);
    $services = $create->handle('header', linkItem('Services'));
    $design = $create->handle('header', linkItem('Design'), $services);

    app(MoveMenuItem::class)->handle($services, $design);
})->throws(InvalidMenuTree::class);

it('enforces the maximum depth when moving', function (): void {
    $create = app(CreateMenuItem::class);
    $company = $create->handle('footer', headingItem('Company'));
    $about = $create->handle('footer', linkItem('About'), $company);
    $jobs = $create->handle('footer', linkItem('Jobs'), $company);

    app(MoveMenuItem::class)->handle($jobs, $about);
})->throws(InvalidMenuTree::class);

it('deletes a leaf', function (): void {
    $create = app(CreateMenuItem::class);
    $create->handle('header', linkItem('Home'));
    $about = $create->handle('header', linkItem('About'));

    expect(app(DeleteMenuItem::class)->handle($about))->toBe(1)
        ->and(persisted('header'))->toBe([['Home', null, 0]]);
});

it('deletes a complete subtree', function (): void {
    $create = app(CreateMenuItem::class);
    $services = $create->handle('header', linkItem('Services'));
    $design = $create->handle('header', linkItem('Design'), $services);
    $create->handle('header', linkItem('Logos'), $design);
    $create->handle('header', linkItem('Development'), $services);
    $create->handle('header', linkItem('Contact'));

    expect(app(DeleteMenuItem::class)->handle($services->id))->toBe(4)
        ->and(persisted('header'))->toBe([['Contact', null, 0]]);
});

it('protects subtrees from accidental cascading deletes', function (): void {
    $parent = app(CreateMenuItem::class)->handle('header', linkItem('Parent'));
    app(CreateMenuItem::class)->handle('header', linkItem('Child'), $parent);

    expect(fn () => $parent->delete())->toThrow(Illuminate\Database\QueryException::class)
        ->and(MenuItem::query()->count())->toBe(2);
});

it('publishes complete trees atomically', function (): void {
    Menu::sync('header', [linkItem('Home'), linkItem('About')]);
    $before = persisted('header');

    $tree = app(MenuRepository::class)->load('header');
    $tree->replace($tree->get($tree->roots()[0])->with(['label' => 'Changed']));
    $tree->add(Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode::new(['type' => 'link', 'label' => '', 'data' => ['link_type' => 'url', 'url' => '/']]));

    expect(fn () => app(PublishMenuTree::class)->handle($tree))->toThrow(InvalidMenuTree::class)
        ->and(persisted('header'))->toBe($before);
});

it('rolls back everything when writing fails halfway', function (): void {
    Menu::sync('header', [linkItem('Home'), linkItem('About')]);
    $before = persisted('header');

    $tree = app(MenuRepository::class)->load('header');
    $tree->replace($tree->get($tree->roots()[0])->with(['label' => 'Changed']));
    $tree->add(Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode::new(linkItem('New')));

    MenuItem::creating(function (): never {
        throw new RuntimeException('Database failure');
    });

    expect(fn () => app(PublishMenuTree::class)->handle($tree))->toThrow(RuntimeException::class)
        ->and(persisted('header'))->toBe($before);
});

it('refuses to publish over changes made by someone else', function (): void {
    Menu::sync('header', [linkItem('Home')]);

    $tree = app(MenuRepository::class)->load('header');
    $fingerprint = $tree->fingerprint();

    app(CreateMenuItem::class)->handle('header', linkItem('Added by a colleague'));

    app(PublishMenuTree::class)->handle($tree, $fingerprint);
})->throws(StaleMenu::class);

it('only writes items that changed', function (): void {
    Menu::sync('header', [linkItem('Home'), linkItem('About'), linkItem('Contact')]);

    $tree = app(MenuRepository::class)->load('header');
    $tree->replace($tree->get($tree->roots()[1])->with(['label' => 'About us']));

    $result = app(PublishMenuTree::class)->handle($tree);

    expect([$result->created, $result->updated, $result->deleted])->toBe([0, 1, 0]);
});

it('dispatches an event after publishing', function (): void {
    Event::fake([MenuPublished::class]);

    Menu::sync('header', [linkItem('Home')]);

    Event::assertDispatched(MenuPublished::class, fn (MenuPublished $event): bool => $event->placement === 'header' && $event->result->created === 1);
});

it('syncs a complete menu from nested arrays', function (): void {
    Menu::sync('footer', [
        headingItem('Company', ['children' => [linkItem('About'), linkItem('Jobs')]]),
    ]);

    $result = Menu::sync('footer', [
        headingItem('Legal', ['children' => [linkItem('Privacy')]]),
    ]);

    expect($result->deleted)->toBe(3)
        ->and(persisted('footer'))->toBe([['Legal', null, 0], ['Privacy', 'Legal', 0]]);
});

it('never touches other placements', function (): void {
    Menu::sync('header', [linkItem('Home')]);
    Menu::sync('footer', [headingItem('Company')]);

    Menu::sync('header', []);

    expect(persisted('header'))->toBe([])
        ->and(persisted('footer'))->toBe([['Company', null, 0]]);
});

it('keeps sort orders contiguous', function (): void {
    $tree = MenuTree::fromNestedArray('header', [linkItem('A'), linkItem('B'), linkItem('C')]);
    app(PublishMenuTree::class)->handle($tree);

    $tree = app(MenuRepository::class)->load('header');
    $tree->remove($tree->roots()[1]);
    app(PublishMenuTree::class)->handle($tree);

    expect(persisted('header'))->toBe([['A', null, 0], ['C', null, 1]]);
});
