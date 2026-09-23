<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Actions\CreateMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore;
use Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraftManager;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\DraftNotFound;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\StaleMenu;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;

beforeEach(function (): void {
    registerTestPlacements();

    Menu::sync('header', [linkItem('Home', '/'), linkItem('About', '/about')]);

    $this->drafts = app(MenuDraftManager::class);
    $this->editor = app(MenuEditor::class);
});

it('starts a clean draft from the persisted tree', function (): void {
    $draft = $this->drafts->start('header');

    expect($draft->placement())->toBe('header')
        ->and($draft->tree->count())->toBe(2)
        ->and($draft->isDirty())->toBeFalse()
        ->and($this->drafts->find($draft->id)->tree->toArray())->toBe($draft->tree->toArray());
});

it('keeps changes in the draft store', function (): void {
    $draft = $this->drafts->start('header');

    $this->editor->create($draft->tree, linkItem('Pricing', '/pricing'));
    $this->drafts->save($draft);

    $restored = $this->drafts->find($draft->id);

    expect($restored->tree->count())->toBe(3)
        ->and($restored->isDirty())->toBeTrue();
});

it('does not affect the published menu before saving', function (): void {
    $draft = $this->drafts->start('header');

    $this->editor->create($draft->tree, linkItem('Pricing', '/pricing'));
    $this->editor->delete($draft->tree, $draft->tree->roots()[0]);
    $this->drafts->save($draft);

    expect(MenuItem::query()->pluck('label')->all())->toBe(['Home', 'About'])
        ->and(Menu::build('header')->pluck('label')->all())->toBe(['Home', 'About']);
});

it('publishes the draft when saved', function (): void {
    $draft = $this->drafts->start('header');
    [$home, $about] = $draft->tree->roots();

    $this->editor->create($draft->tree, linkItem('Team', '/team'), $about);
    $this->editor->reorder($draft->tree, null, [$about, $home]);
    $this->drafts->save($draft);

    $result = $this->drafts->publish($draft);

    expect($result->created)->toBe(1)
        ->and($draft->isDirty())->toBeFalse()
        ->and($draft->tree->childrenOf($draft->tree->roots()[0]))->toHaveCount(1)
        ->and(Menu::build('header')->pluck('label')->all())->toBe(['About', 'Home'])
        ->and(Menu::build('header')->first()->children[0]->label)->toBe('Team');
});

it('restores the published state when discarding', function (): void {
    $draft = $this->drafts->start('header');

    $this->editor->delete($draft->tree, $draft->tree->roots()[0]);
    $this->drafts->save($draft);

    $this->drafts->discard($draft);

    expect($draft->isDirty())->toBeFalse()
        ->and($draft->tree->count())->toBe(2)
        ->and($this->drafts->find($draft->id)->tree->count())->toBe(2);
});

it('discards to the latest persisted state, not a stale copy', function (): void {
    $draft = $this->drafts->start('header');

    app(CreateMenuItem::class)->handle('header', linkItem('Added elsewhere', '/elsewhere'));

    $this->drafts->discard($draft);

    expect($draft->tree->count())->toBe(3);
});

it('publishes atomically when the draft is invalid', function (): void {
    $draft = $this->drafts->start('header');

    // Bypass the editor to simulate a corrupted draft.
    $draft->tree->add(MenuNode::new(['type' => 'link', 'label' => 'Broken', 'data' => ['link_type' => 'route', 'route' => 'missing']]));
    $draft->tree->replace($draft->tree->get($draft->tree->roots()[0])->with(['label' => 'Renamed']));

    expect(fn () => $this->drafts->publish($draft))->toThrow(InvalidMenuTree::class)
        ->and(MenuItem::query()->pluck('label')->all())->toBe(['Home', 'About'])
        ->and($draft->isDirty())->toBeTrue();
});

it('refuses to publish over a menu that changed in the meantime', function (): void {
    $first = $this->drafts->start('header');
    $second = $this->drafts->start('header');

    $this->editor->create($first->tree, linkItem('First', '/first'));
    $this->drafts->publish($first);

    $this->editor->create($second->tree, linkItem('Second', '/second'));

    expect(fn () => $this->drafts->publish($second))->toThrow(StaleMenu::class)
        ->and(MenuItem::query()->pluck('label')->all())->toBe(['Home', 'About', 'First']);
});

it('keeps drafts of administrators separate', function (): void {
    $first = $this->drafts->start('header');
    $second = $this->drafts->start('header');

    $this->editor->create($first->tree, linkItem('Only in first', '/'));
    $this->drafts->save($first);

    expect($this->drafts->find($second->id)->tree->count())->toBe(2);
});

it('forgets drafts', function (): void {
    $draft = $this->drafts->start('header');

    $this->drafts->forget($draft);

    $this->drafts->find($draft->id);
})->throws(DraftNotFound::class);

it('never caches drafts as published data', function (): void {
    Menu::build('header');

    $draft = $this->drafts->start('header');
    $this->editor->create($draft->tree, linkItem('Draft only', '/draft'));
    $this->drafts->save($draft);

    expect(Menu::build('header')->pluck('label')->all())->toBe(['Home', 'About']);
});

it('allows swapping the draft store', function (): void {
    $store = new class implements DraftStore
    {
        public array $drafts = [];

        public function find(string $id): ?Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraft
        {
            return $this->drafts[$id] ?? null;
        }

        public function put(Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraft $draft): void
        {
            $this->drafts[$draft->id] = $draft;
        }

        public function forget(string $id): void
        {
            unset($this->drafts[$id]);
        }
    };

    app()->instance(DraftStore::class, $store);

    $draft = app(MenuDraftManager::class)->start('header');

    expect($store->drafts)->toHaveKey($draft->id);
});
