<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

use Syriable\Filament\Plugins\MenuBuilder\Actions\CreateMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore;
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\ManageMenu;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\Category;
use Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures\MenuItemPolicy;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

beforeEach(function (): void {
    registerTestPlacements();

    $this->actingAs(admin());

    Menu::sync('header', [
        linkItem('Home', '/'),
        linkItem('Services', '/services', ['children' => [linkItem('Design', '/design'), linkItem('Development', '/development')]]),
    ]);
});

function keyOf(string $label): string
{
    return MenuNode::keyForId((int) MenuItem::query()->where('label', $label)->value('id'));
}

function draftOf(Livewire\Features\SupportTesting\Testable $component): MenuTree
{
    return app(DraftStore::class)->find($component->get('draftId'))->tree;
}

/**
 * @return list<string|null>
 */
function draftLabels(MenuTree $tree, ?string $parent = null): array
{
    return array_map(fn (string $key): ?string => $tree->get($key)->label, $tree->childrenOf($parent));
}

function publishedLabels(): array
{
    return MenuItem::query()->whereNull('parent_id')->where('placement', 'header')->orderBy('sort_order')->pluck('label')->all();
}

it('renders the tree of the placement', function (): void {
    $this->get(ManageMenu::getUrl(['placement' => 'header']))
        ->assertOk()
        ->assertSeeInOrder(['Header', 'Home', 'Services', 'Design', 'Development'])
        ->assertSee('Saved');
});

it('can be inspected without being mounted', function (): void {
    // Filament Shield instantiates every page to read its title.
    $page = new ManageMenu;

    expect($page->getTitle())->toBe('Menu')
        ->and($page->getSubheading())->toBeNull()
        ->and($page->getBreadcrumbs())->toContain('Menu')
        ->and($page->findPlacement())->toBeNull();
});

it('returns 404 for unknown placements', function (): void {
    $this->get(ManageMenu::getUrl(['placement' => 'nowhere']))->assertNotFound();
});

it('renders an empty placement', function (): void {
    livewire(ManageMenu::class, ['placement' => 'sidebar'])
        ->assertOk()
        ->assertSee('This menu has no items yet.');
});

it('creates items in the draft only', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->callAction('createItem', data: [
            'type' => 'link',
            'label' => 'Pricing',
            'data' => ['link_type' => 'url', 'url' => '/pricing'],
        ])
        ->assertHasNoActionErrors()
        ->assertSee('Unsaved changes')
        ->assertActionEnabled('save');

    expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services', 'Pricing'])
        ->and(publishedLabels())->toBe(['Home', 'Services'])
        ->and(Menu::build('header')->pluck('label')->all())->toBe(['Home', 'Services']);
});

it('creates child items', function (): void {
    $services = keyOf('Services');

    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->callAction(TestAction::make('createItem')->arguments(['parent' => $services]), data: [
            'type' => 'heading',
            'label' => 'More',
        ])
        ->assertHasNoActionErrors();

    expect(draftLabels(draftOf($component), $services))->toBe(['Design', 'Development', 'More']);
});

it('creates route links with parameters', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->callAction('createItem', data: [
            'type' => 'link',
            'label' => 'Profile',
            'data' => ['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => ['user' => '7']],
        ])
        ->assertHasNoActionErrors();

    $tree = draftOf($component);
    $node = $tree->get($tree->roots()[2]);

    expect($node->data)->toMatchArray([
        'link_type' => 'route',
        'route' => 'users.show',
        'route_parameters' => ['user' => '7'],
    ]);
});

it('hydrates the fields of the chosen type', function (): void {
    livewire(ManageMenu::class, ['placement' => 'header'])
        ->mountAction('createItem')
        ->fillForm(['type' => 'heading'])
        ->fillForm(['type' => 'link'])
        ->assertSchemaStateSet(['data.link_type' => 'url']);
});

it('shows server side rule violations on the form fields', function (): void {
    livewire(ManageMenu::class, ['placement' => 'header'])
        ->callAction('createItem', data: [
            'type' => 'link',
            'label' => 'Profile',
            'data' => ['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => []],
        ])
        ->assertHasActionErrors(['data.route']);
});

it('validates the item form', function (): void {
    livewire(ManageMenu::class, ['placement' => 'header'])
        ->callAction('createItem', data: [
            'type' => 'link',
            'label' => '',
            'data' => ['link_type' => 'url', 'url' => ''],
        ])
        ->assertHasActionErrors(['label' => 'required', 'data.url' => 'required']);
});

it('only offers the item types allowed by the placement', function (): void {
    Menu::sync('footer', [headingItem('Company')]);

    $component = livewire(ManageMenu::class, ['placement' => 'footer']);

    // Root of the footer only accepts headings: the type is fixed.
    $component
        ->callAction('createItem', data: ['label' => 'Legal'])
        ->assertHasNoActionErrors();

    $tree = draftOf($component);

    expect($tree->get($tree->roots()[1])->type)->toBe('heading');
});

it('rejects item types the placement does not allow', function (): void {
    Menu::registerItemType(MenuItemType::make('group')->withoutUrl());
    Menu::registerPlacement(
        Syriable\Filament\Plugins\MenuBuilder\MenuPlacement::make('sidebar')->rootItemTypes(['group', 'link']),
    );

    $component = livewire(ManageMenu::class, ['placement' => 'sidebar'])
        ->callAction('createItem', data: ['type' => 'heading', 'label' => 'Not allowed'])
        ->assertHasActionErrors(['type']);

    expect(draftOf($component)->count())->toBe(0);
});

it('does not offer child items below items at the maximum depth', function (): void {
    Menu::sync('footer', [headingItem('Company', ['children' => [linkItem('About', '/about')]])]);

    livewire(ManageMenu::class, ['placement' => 'footer'])
        ->assertSeeHtml('mountAction(\'createItem\', '.Illuminate\Support\Js::from(['parent' => keyOf('Company')]))
        ->assertDontSeeHtml('mountAction(\'createItem\', '.Illuminate\Support\Js::from(['parent' => keyOf('About')]));
});

it('edits items', function (): void {
    $home = keyOf('Home');

    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->mountAction(TestAction::make('editItem')->arguments(['key' => $home]))
        ->assertSchemaStateSet(['label' => 'Home', 'data.url' => '/'])
        ->callMountedAction()
        ->callAction(TestAction::make('editItem')->arguments(['key' => $home]), data: [
            'label' => 'Start',
            'badge' => 'New',
            'visibility' => 'guests',
        ])
        ->assertHasNoActionErrors();

    $node = draftOf($component)->get($home);

    expect($node->label)->toBe('Start')
        ->and($node->badge)->toBe('New')
        ->and($node->visibility)->toBe('guests')
        ->and(MenuItem::query()->where('label', 'Start')->exists())->toBeFalse();
});

it('deletes items with their descendants from the draft', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->mountAction(TestAction::make('deleteItem')->arguments(['key' => keyOf('Services')]))
        ->assertMountedActionModalSee('This item contains 2 child items. Deleting it will also delete its descendants.')
        ->callMountedAction();

    expect(draftLabels(draftOf($component)))->toBe(['Home'])
        ->and(MenuItem::query()->count())->toBe(4);
});

it('moves items with drag and drop', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Development'), keyOf('Design'), 'inside');

    expect(draftLabels(draftOf($component), keyOf('Design')))->toBe(['Development']);

    $component->call('moveItem', keyOf('Development'), keyOf('Home'), 'before');

    expect(draftLabels(draftOf($component)))->toBe(['Development', 'Home', 'Services'])
        ->and(publishedLabels())->toBe(['Home', 'Services']);
});

it('rejects drops below descendants', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Services'), keyOf('Design'), 'inside')
        ->assertNotified('The menu cannot be changed like that');

    expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services']);
});

it('rejects drops that break the maximum depth', function (): void {
    Menu::sync('footer', [
        headingItem('Company', ['children' => [linkItem('About', '/about'), linkItem('Jobs', '/jobs')]]),
    ]);

    $component = livewire(ManageMenu::class, ['placement' => 'footer'])
        ->call('moveItem', keyOf('Jobs'), keyOf('About'), 'inside')
        ->assertNotified();

    expect(draftLabels(draftOf($component), keyOf('Company')))->toBe(['About', 'Jobs']);
});

it('ignores invalid drop positions', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Home'), keyOf('Services'), 'sideways');

    expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services']);
});

it('saves the draft', function (): void {
    livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Home'), keyOf('Services'), 'after')
        ->callAction('save')
        ->assertNotified('Menu saved')
        ->assertSee('Saved')
        ->assertActionDisabled('save');

    expect(publishedLabels())->toBe(['Services', 'Home'])
        ->and(Menu::build('header')->pluck('label')->all())->toBe(['Services', 'Home']);
});

it('disables save and discard without changes', function (): void {
    livewire(ManageMenu::class, ['placement' => 'header'])
        ->assertActionDisabled('save')
        ->assertActionDisabled('discard');
});

it('discards the draft', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Home'), keyOf('Services'), 'after')
        ->callAction('discard')
        ->assertNotified('Changes discarded')
        ->assertActionDisabled('save');

    expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services']);
});

it('refuses to save over changes of another administrator', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header'])
        ->call('moveItem', keyOf('Home'), keyOf('Services'), 'after');

    app(CreateMenuItem::class)->handle('header', linkItem('Colleague', '/colleague'));

    $component->callAction('save')->assertNotified('The menu was changed by someone else');

    expect(publishedLabels())->toBe(['Home', 'Services', 'Colleague']);
});

it('starts a new draft when the draft expired', function (): void {
    $component = livewire(ManageMenu::class, ['placement' => 'header']);
    $expired = $component->get('draftId');

    app(DraftStore::class)->forget($expired);

    $component->call('moveItem', keyOf('Home'), keyOf('Services'), 'after')
        ->assertNotified('Your draft expired, so the published menu was loaded again.');

    expect($component->get('draftId'))->not->toBe($expired);
});

it('shows the titles of linked records', function (): void {
    Menu::registerItemType(
        MenuItemType::make('category')
            ->model(Category::class, titleAttribute: 'name')
            ->resolveUrlUsing(fn (Category $record): string => '/categories/'.$record->slug),
    );

    $category = Category::query()->create(['name' => 'Garden furniture', 'slug' => 'garden']);

    Menu::sync('sidebar', [['type' => 'category', 'data' => ['record_id' => $category->id]]]);

    livewire(ManageMenu::class, ['placement' => 'sidebar'])->assertSee('Garden furniture');
});

it('creates model backed items', function (): void {
    Menu::registerItemType(
        MenuItemType::make('category')
            ->model(Category::class, titleAttribute: 'name')
            ->resolveUrlUsing(fn (Category $record): string => '/categories/'.$record->slug),
    );

    $category = Category::query()->create(['name' => 'Shoes', 'slug' => 'shoes']);

    livewire(ManageMenu::class, ['placement' => 'sidebar'])
        ->callAction('createItem', data: ['type' => 'category', 'data' => ['record_id' => $category->id]])
        ->assertHasNoActionErrors()
        ->callAction('save');

    expect(Menu::build('sidebar')->first()?->label)->toBe('Shoes');
});

describe('authorization', function (): void {
    beforeEach(function (): void {
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
    });

    it('denies access to placements the user may not view', function (): void {
        MenuItemPolicy::$denied = ['viewAny:header'];

        $this->get(ManageMenu::getUrl(['placement' => 'header']))->assertForbidden();
    });

    it('hides and blocks actions the user may not perform', function (string $ability, string $action): void {
        MenuItemPolicy::$denied = ["{$ability}:header"];

        livewire(ManageMenu::class, ['placement' => 'header'])->assertActionHidden(
            TestAction::make($action)->arguments(['key' => keyOf('Home')]),
        );
    })->with([
        ['create', 'createItem'],
        ['update', 'editItem'],
        ['delete', 'deleteItem'],
        ['publish', 'save'],
    ]);

    it('blocks reordering', function (): void {
        MenuItemPolicy::$denied = ['reorder:header'];

        $component = livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('moveItem', keyOf('Home'), keyOf('Services'), 'after')
            ->assertNotified('You are not allowed to do this.');

        expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services']);
    });

    it('never publishes without permission', function (): void {
        MenuItemPolicy::$denied = ['publish:header'];

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('moveItem', keyOf('Home'), keyOf('Services'), 'after')
            ->call('mountAction', 'save')
            ->call('callMountedAction');

        expect(publishedLabels())->toBe(['Home', 'Services']);
    });

    it('never mutates the draft through blocked actions', function (): void {
        MenuItemPolicy::$denied = ['delete:header'];

        $component = livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('mountAction', 'deleteItem', ['key' => keyOf('Home')])
            ->call('callMountedAction');

        expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services']);
    });

    it('scopes permissions per placement', function (): void {
        MenuItemPolicy::$denied = ['create:header'];

        livewire(ManageMenu::class, ['placement' => 'sidebar'])->assertActionVisible('createItem');
    });
});
