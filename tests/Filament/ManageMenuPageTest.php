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

describe('rendering options', function (): void {
    it('stores arbitrary attributes, render target and badge position from the form', function (): void {
        $home = keyOf('Home');

        $component = livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction(TestAction::make('editItem')->arguments(['key' => $home]), data: [
                'badge' => 'New',
                'data' => [
                    'badge_position' => 'top',
                    'attribute_target' => 'wrapper',
                    'attributes' => ['class' => 'btn btn-primary', 'data-modal' => 'login', 'x-on:click' => 'open = true'],
                ],
            ])
            ->assertHasNoActionErrors()
            ->callAction('save');

        $item = Menu::build('header')->first();

        expect(draftOf($component)->get($home)->data)->toMatchArray(['url' => '/', 'badge_position' => 'top'])
            ->and($item->attributes)->toBe(['class' => 'btn btn-primary', 'data-modal' => 'login', 'x-on:click' => 'open = true'])
            ->and($item->attributeTarget->value)->toBe('wrapper')
            ->and($item->badgePosition->value)->toBe('top');
    });

    it('rejects malformed attribute names in the form', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction(TestAction::make('editItem')->arguments(['key' => keyOf('Home')]), data: [
                'data' => ['attributes' => ['bad name' => 'x']],
            ])
            ->assertHasActionErrors(['data.attributes']);
    });

    it('offers heading, link and button when creating an item', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldExists('type', fn ($field): bool => array_keys($field->getOptions()) === ['heading', 'link', 'button']);
    });

    it('creates buttons with color, size and attributes', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction('createItem', data: [
                'type' => 'button',
                'label' => 'Login',
                'color' => 'success',
                'data' => [
                    'size' => 'lg',
                    'outlined' => true,
                    'icon_position' => 'after',
                    'attributes' => ['data-modal' => 'login', 'x-on:click' => "\$dispatch('open-login')"],
                ],
            ])
            ->assertHasNoActionErrors()
            ->callAction('save');

        $login = Menu::build('header')->last();

        expect($login->type)->toBe('button')
            ->and($login->isButton())->toBeTrue()
            ->and($login->url)->toBeNull()
            ->and($login->color)->toBe('success')
            ->and($login->buttonOption('size'))->toBe('lg')
            ->and($login->buttonOption('outlined'))->toBeTrue()
            ->and($login->buttonOption('icon_position'))->toBe('after')
            ->and($login->attributes)->toBe(['data-modal' => 'login', 'x-on:click' => "\$dispatch('open-login')"]);
    });

    it('creates buttons that link somewhere', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction('createItem', data: ['type' => 'button', 'label' => 'Sign up', 'data' => ['url' => '/register']])
            ->assertHasNoActionErrors()
            ->callAction('save');

        expect(Menu::build('header')->last()->url)->toBe('/register');
    });

    it('shows the attribute editor for every item type', function (string $type): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->fillForm(['type' => $type])
            ->assertFormFieldVisible('item-fields.data.attributes');
    })->with(['heading', 'link', 'button']);

    it('shows the button settings only for buttons', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->fillForm(['type' => 'button'])
            ->assertFormFieldVisible('item-fields.data.size')
            ->fillForm(['type' => 'link'])
            ->assertFormFieldDoesNotExist('item-fields.data.size');
    });
});

describe('moving items out of their parent', function (): void {
    it('moves a child to the root after its parent (outdent)', function (): void {
        $component = livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('moveItem', keyOf('Development'), keyOf('Services'), 'after');

        expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services', 'Development'])
            ->and(draftLabels(draftOf($component), keyOf('Services')))->toBe(['Design']);
    });

    it('moves a nested child to the end of the root level', function (): void {
        $component = livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('moveItem', keyOf('Development'), keyOf('Design'), 'inside')
            ->call('moveItem', keyOf('Development'), keyOf('Services'), 'after');

        expect(draftLabels(draftOf($component)))->toBe(['Home', 'Services', 'Development'])
            ->and(draftLabels(draftOf($component), keyOf('Design')))->toBe([]);
    });

    it('persists the new parent when saved', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->call('moveItem', keyOf('Design'), keyOf('Services'), 'after')
            ->call('moveItem', keyOf('Home'), keyOf('Services'), 'inside')
            ->callAction('save');

        expect(publishedLabels())->toBe(['Services', 'Design'])
            ->and(MenuItem::query()->where('label', 'Design')->value('parent_id'))->toBeNull()
            ->and(MenuItem::query()->where('label', 'Home')->value('parent_id'))->toBe(MenuItem::query()->where('label', 'Services')->value('id'));
    });

    it('offers a root drop zone to editors who may reorder', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])->assertSeeHtml('data-root-drop');

        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        MenuItemPolicy::$denied = ['reorder:header'];

        livewire(ManageMenu::class, ['placement' => 'header'])->assertDontSeeHtml('data-root-drop');
    });
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

describe('item form', function (): void {
    it('opens in a slide-over by default', function (): void {
        $page = livewire(ManageMenu::class, ['placement' => 'header'])->instance();

        expect($page->getAction('createItem')->isModalSlideOver())->toBeTrue()
            ->and($page->getAction('editItem')->isModalSlideOver())->toBeTrue();
    });

    it('can open in a modal instead', function (): void {
        filament()->getPanel('admin')->getPlugin('menu-builder')->slideOver(false);

        $page = livewire(ManageMenu::class, ['placement' => 'header'])->instance();

        expect($page->getAction('createItem')->isModalSlideOver())->toBeFalse();
    });

    it('reads the display mode and width from the config', function (): void {
        config()->set('menu-builder.item_form', ['slide_over' => false, 'width' => '4xl']);

        $page = livewire(ManageMenu::class, ['placement' => 'header'])->instance();

        expect($page->getAction('createItem')->isModalSlideOver())->toBeFalse()
            ->and($page->getAction('createItem')->getModalWidth())->toBe(Filament\Support\Enums\Width::FourExtraLarge)
            ->and($page->getAction('editItem')->getModalWidth())->toBe(Filament\Support\Enums\Width::FourExtraLarge);
    });

    it('uses a 2xl slide-over for missing or invalid config values', function (): void {
        config()->set('menu-builder.item_form', ['width' => 'huge']);

        $page = livewire(ManageMenu::class, ['placement' => 'header'])->instance();

        expect($page->getAction('createItem')->isModalSlideOver())->toBeTrue()
            ->and($page->getAction('createItem')->getModalWidth())->toBe(Filament\Support\Enums\Width::TwoExtraLarge);
    });

    it('lets the plugin override the config', function (): void {
        config()->set('menu-builder.item_form', ['slide_over' => true, 'width' => 'md']);
        filament()->getPanel('admin')->getPlugin('menu-builder')->slideOver(false)->modalWidth(Filament\Support\Enums\Width::SevenExtraLarge);

        $page = livewire(ManageMenu::class, ['placement' => 'header'])->instance();

        expect($page->getAction('createItem')->isModalSlideOver())->toBeFalse()
            ->and($page->getAction('createItem')->getModalWidth())->toBe(Filament\Support\Enums\Width::SevenExtraLarge);
    });

    it('reads the translation locales from the config', function (): void {
        config()->set('menu-builder.locales', ['ar', 'fr' => 'Français']);

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldVisible('item-fields.data.label_translations.ar')
            ->assertFormFieldVisible('item-fields.data.label_translations.fr');
    });

    it('prefers the locales of the plugin', function (): void {
        config()->set('menu-builder.locales', ['fr']);
        filament()->getPanel('admin')->getPlugin('menu-builder')->locales(['de']);

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldVisible('item-fields.data.label_translations.de')
            ->assertFormFieldDoesNotExist('item-fields.data.label_translations.fr');
    });

    it('stores the text style from the form', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldVisible('item-fields.data.text_style.weight')
            ->assertFormFieldVisible('item-fields.data.text_style.hover_color')
            ->callMountedAction();

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction('createItem', data: [
                'type' => 'link',
                'label' => 'Pricing',
                'data' => ['link_type' => 'url', 'url' => '/pricing', 'text_style' => [
                    'weight' => 'semibold',
                    'underline' => 'none',
                    'cursor' => 'pointer',
                    'italic' => true,
                    'hover_color' => '#f59e0b',
                ]],
            ])
            ->assertHasNoActionErrors()
            ->callAction('save');

        expect(Menu::build('header')->last()->textStyle->toArray())->toBe([
            'weight' => 'semibold',
            'italic' => true,
            'underline' => 'none',
            'cursor' => 'pointer',
            'hover_color' => '#f59e0b',
        ]);
    });

    it('rejects invalid hover colors', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction('createItem', data: [
                'type' => 'link',
                'label' => 'Pricing',
                'data' => ['link_type' => 'url', 'url' => '/pricing', 'text_style' => ['hover_color' => 'red;}']],
            ])
            ->assertHasActionErrors(['data.text_style.hover_color']);
    });

    it('has no translation fields without locales', function (): void {
        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldDoesNotExist('item-fields.data.label_translations.ar');
    });

    it('stores label translations for the configured locales', function (): void {
        filament()->getPanel('admin')->getPlugin('menu-builder')->locales(['en' => 'English', 'ar' => 'العربية']);

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->mountAction('createItem')
            ->assertFormFieldVisible('item-fields.data.label_translations.en')
            ->assertFormFieldVisible('item-fields.data.label_translations.ar')
            ->callMountedAction()
            ->assertHasActionErrors(['label']);

        livewire(ManageMenu::class, ['placement' => 'header'])
            ->callAction('createItem', data: [
                'type' => 'link',
                'label' => 'Pricing',
                'data' => ['link_type' => 'url', 'url' => '/pricing', 'label_translations' => ['ar' => 'الأسعار']],
            ])
            ->assertHasNoActionErrors()
            ->callAction('save');

        expect(Menu::build('header', locale: 'ar')->last()->label)->toBe('الأسعار')
            ->and(Menu::build('header', locale: 'en')->last()->label)->toBe('Pricing');
    });
});
