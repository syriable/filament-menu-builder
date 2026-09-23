# Filament Menu Builder

A lightweight menu manager for [Filament](https://filamentphp.com). It gives administrators a drag & drop tree editor with drafts and an explicit save, and it gives developers a small, strictly validated menu engine with a clean frontend API.

- **Placements are yours.** Register `header`, `footer`, `sidebar`, `account-menu` or anything else in code or config. Each placement can have its own rules, such as maximum depth, allowed root types or allowed child types.
- **Item types are extensible.** Two types ship with the package: `heading`, and `link` (URL, or named route with parameters). You can register your own types, including types backed by any Eloquent model.
- **Drafts first.** Every edit and every drag & drop goes into a server-side draft. Nothing is published until someone clicks **Save changes**. The whole tree is then validated and saved in a single transaction.
- **One rule engine.** The Filament editor, drag & drop, seeders and the programmatic API all validate through the same `MenuTreeGuard`.
- **Fast frontend.** `Menu::build('header')` makes one query per placement, is cached, and builds the tree in O(n).

```php
$menu = Menu::build('header'); // Collection of ResolvedMenuItem
```

## Requirements

| Package | Version |
| --- | --- |
| PHP | 8.4+ |
| Laravel | 13+ |
| Filament | 5+ |
| Livewire | 4+ |

There is nothing to build on the frontend. The editor uses Livewire, Alpine.js, native browser drag & drop and a small stylesheet based on Filament's CSS variables.

## Installation

```bash
composer require syriable/filament-menu-builder
```

Publish the config and the migration, then run the migration:

```bash
php artisan menu-builder:install
```

or do the same steps by hand:

```bash
php artisan vendor:publish --tag="menu-builder-config"
php artisan vendor:publish --tag="menu-builder-migrations"
php artisan migrate
```

Register the plugin in a panel:

```php
use Syriable\Filament\Plugins\MenuBuilder\MenuBuilderPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            MenuBuilderPlugin::make()
                ->navigationGroup('Content')   // optional
                ->navigationSort(10)           // optional
                ->navigationIcon('heroicon-o-bars-3') // optional
                ->navigationLabel('Menus')     // optional
                ->navigation(true),            // set to false to hide the navigation item
        );
}
```

If you deploy without running `composer install` hooks, publish the plugin's assets:

```bash
php artisan filament:assets
```

## Registering placements

A placement is a named location where your application renders a menu. Placements are configuration, not database records. Register them from a service provider:

```php
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;

public function boot(): void
{
    Menu::registerPlacement(
        MenuPlacement::make('header')
            ->label('Header')
            ->icon('heroicon-o-bars-3'),

        MenuPlacement::make('footer')
            ->label('Footer')
            ->description('Links at the bottom of every page')
            ->icon('heroicon-o-rectangle-group')
            ->maxDepth(2)
            ->rootItemTypes(['heading'])        // only headings at the top level
            ->childItemTypes('heading', ['link']) // headings contain links
            ->childItemTypes('link', []),       // links have no children

        MenuPlacement::make('sidebar')
            ->label('Sidebar')
            ->maxDepth(3)
            ->sort(30),
    );
}
```

Every registered placement appears automatically on the **Menus** page in Filament.

| Method | Purpose |
| --- | --- |
| `label()`, `description()`, `icon()` | How the placement is shown in Filament. By default the label is the key in headline case. |
| `sort()` | Order on the Menus page. Placements with the same sort keep their registration order. |
| `maxDepth(?int)` | Maximum number of levels. `1` means root items only, and `null` (the default) means unlimited. |
| `itemTypes(?array)` | Item types allowed anywhere in the placement. `null` (the default) allows all of them. |
| `rootItemTypes(?array)` | Item types allowed at the top level. |
| `childItemTypes(string $parentType, array $types)` | Item types allowed below a parent type. An empty array means that type cannot have children. |

Simple placements can also be declared in `config/menu-builder.php`:

```php
'placements' => [
    'header' => ['label' => 'Header', 'icon' => 'heroicon-o-bars-3'],
    'footer' => [
        'label' => 'Footer',
        'max_depth' => 2,
        'root_item_types' => ['heading'],
        'child_item_types' => ['heading' => ['link'], 'link' => []],
    ],
],
```

A placement registered in code replaces a config placement with the same key.

## Link items

The built-in `link` type points either to a URL or to a named route.

- **URL**: accepted exactly as entered. Examples: `/pricing`, `https://example.com`, `#pricing`, `mailto:hello@example.com`, `tel:+46123456789`.
- **Route**: picked from a searchable list of the application's named `GET` routes. Route parameters are edited as key/value pairs, and choosing a route pre-fills the names of its parameters. Parameters the route does not declare are added as a query string.

Link data is stored in the item's `data` column:

```php
['link_type' => 'url', 'url' => '/pricing', 'new_tab' => false]
['link_type' => 'route', 'route' => 'users.show', 'route_parameters' => ['user' => 123]]
```

Saving fails when a route does not exist or cannot be generated with the given parameters. If a route is removed from the application later, the item is left out of the built menu instead of breaking the page. Routes that match the patterns in `menu-builder.routes.exclude` (for example `filament.*` or `livewire.*`) are not offered in the route picker.

## Custom item types

An item type defines its form fields, its validation rules and how its label and URL are resolved. Type-specific values are stored in the item's `data` JSON column, so custom types do not need migrations.

```php
use Filament\Forms\Components\TextInput;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;

Menu::registerItemType(
    MenuItemType::make('page')
        ->label('Page')
        ->icon('heroicon-o-document')
        ->schema([                        // Filament components, bound to `data`
            TextInput::make('slug')->required(),
        ])
        ->rules([                         // enforced on every write path, not only in the form
            'slug' => ['required', 'alpha_dash'],
        ])
        ->resolveUrlUsing(fn (array $data): string => url('pages/'.$data['slug'])),
);
```

Other options:

```php
MenuItemType::make('divider')
    ->withoutUrl()               // not a link, like the built-in heading
    ->canHaveChildren(false);    // never allows child items, in any placement
```

Resolver closures can ask for `$item` (the `MenuNode`), `$data` (the item's data array) and `$record` (the linked model; see below).

## Model-backed items

Link items to any Eloquent model, such as categories, pages or documentation sections, without the package knowing that model:

```php
use App\Models\Category;

Menu::registerItemType(
    MenuItemType::make('category')
        ->label('Category')
        ->icon('heroicon-o-tag')
        ->model(
            Category::class,
            titleAttribute: 'name', // used for search and as the default label
            modifyQueryUsing: fn ($query) => $query->where('is_public', true), // optional
        )
        ->resolveUrlUsing(fn (Category $record): string => route('categories.show', $record)),
);
```

- The form gets a searchable record select automatically. The record key is stored as `data.record_id`.
- The label is optional. When it is empty, the menu shows the record's title, so renaming a category renames the menu item too.
- The builder loads the linked records with **one query per type**. Items whose record no longer exists, or is excluded by `modifyQueryUsing`, are left out.
- Use `resolveLabelUsing(fn (Category $record) => ...)` for custom labels.

## Visibility

Every item has a visibility rule. The built-in rules are `everyone`, `guests` and `authenticated`. You can register your own:

```php
use Syriable\Filament\Plugins\MenuBuilder\MenuVisibility;

Menu::registerVisibility(
    MenuVisibility::make('admins', 'Administrators', fn (?Authenticatable $user) => $user?->is_admin === true),
);
```

When an item is invisible or inactive, its whole subtree is hidden. Items with an unknown visibility rule are hidden too, so a missing rule fails closed.

## Building menus on the frontend

```php
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;

$items = Menu::build('header');                  // current user and current URL
$items = Menu::build('header', $user);           // a specific user
$items = Menu::build('header', currentUrl: $url);
```

`build()` returns a `Collection` of `ResolvedMenuItem` objects. Each item is already filtered, labelled and resolved:

| Property / method | |
| --- | --- |
| `id`, `type`, `label`, `url`, `depth` | `url` is `null` for items without a URL, such as headings |
| `icon`, `color`, `badge`, `badgeColor` | presentation hints entered by administrators |
| `openInNewTab` | whether the link should open in a new tab |
| `isCurrent` | the item's URL is the current page (query string ignored) |
| `isActiveTrail` | a descendant is the current page |
| `isActive()` | `isCurrent || isActiveTrail` |
| `children`, `hasChildren()` | nested `ResolvedMenuItem`s |
| `data` | the raw type data, for custom rendering |
| `toArray()` / JSON | for APIs and JavaScript frontends |

A minimal Blade component:

```blade
{{-- resources/views/components/menu.blade.php --}}
@props(['items'])

<ul>
    @foreach ($items as $item)
        <li @class(['active' => $item->isActive()])>
            @if ($item->url)
                <a href="{{ $item->url }}" @if ($item->openInNewTab) target="_blank" rel="noopener" @endif>
                    {{ $item->label }}
                </a>
            @else
                <span>{{ $item->label }}</span>
            @endif

            @if ($item->hasChildren())
                <x-menu :items="$item->children" />
            @endif
        </li>
    @endforeach
</ul>
```

```blade
<x-menu :items="Menu::build('header')" />
```

## Drafts and saving

The editor never writes to the published menu while an administrator is working on it:

1. Opening a placement creates a **draft** from the persisted tree. The draft is stored server-side in Laravel's cache, and the browser only holds a random draft id.
2. Every create, edit, delete and drag & drop is validated and applied to the draft. The page shows **Unsaved changes**, and leaving the page asks for confirmation.
3. **Save changes** validates the complete draft and persists it in one database transaction. It writes only the rows that changed, and it deletes removed items children first. If anything fails, nothing is persisted.
4. **Discard changes** reloads the persisted tree from the database. The database is always the source of truth.

If another administrator saved the same placement after your draft started, saving is refused with a clear message instead of silently overwriting their work.

Drafts expire after `menu-builder.drafts.ttl` seconds (12 hours by default). To keep drafts somewhere else, such as a database table with revisions, bind your own implementation of `Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore`.

## Programmatic API and seeding

Replace a complete placement, for example in a seeder:

```php
Menu::sync('footer', [
    ['type' => 'heading', 'label' => 'Company', 'children' => [
        ['type' => 'link', 'label' => 'About', 'data' => ['link_type' => 'route', 'route' => 'about']],
        ['type' => 'link', 'label' => 'Jobs', 'data' => ['link_type' => 'url', 'url' => '/jobs']],
    ]],
]);
```

Or change single items. Each action validates the placement rules, runs in a transaction and clears the cache:

```php
use Syriable\Filament\Plugins\MenuBuilder\Actions\{CreateMenuItem, UpdateMenuItem, MoveMenuItem, ReorderMenuItems, DeleteMenuItem};

$services = app(CreateMenuItem::class)->handle('header', [
    'type' => 'link', 'label' => 'Services', 'data' => ['link_type' => 'url', 'url' => '/services'],
]);
$design = app(CreateMenuItem::class)->handle('header', [...], parent: $services);

app(UpdateMenuItem::class)->handle($design, ['badge' => 'New']);
app(MoveMenuItem::class)->handle($design, parent: null, position: 0);   // to the root, first
app(ReorderMenuItems::class)->handle('header', parent: null, orderedIds: [3, 1, 2]);
app(DeleteMenuItem::class)->handle($services); // deletes the subtree, returns the count
```

Invalid changes throw `InvalidMenuTree`, which has a `violations` list and an `errorsFor($key)` helper. A successful save dispatches the `MenuPublished` event.

> Writing to the `MenuItem` model directly bypasses validation. Use the actions or `Menu::sync()` instead.

## Authorization

The package uses Laravel's policies and does not add a permission system. Register a policy for the menu item model. Each method receives the placement key:

```php
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;

Gate::policy(MenuItem::class, MenuItemPolicy::class);

class MenuItemPolicy
{
    public function viewAny(User $user, string $placement): bool { return $user->can('manage menus'); }
    public function create(User $user, string $placement): bool  { /* ... */ }
    public function update(User $user, string $placement): bool  { /* ... */ }
    public function reorder(User $user, string $placement): bool { /* ... */ }
    public function delete(User $user, string $placement): bool  { /* ... */ }
    public function publish(User $user, string $placement): bool { /* ... */ } // Save changes
}
```

Once a policy is registered, abilities it does not define are denied. Without a policy, every user who can access the panel may manage menus, which matches Filament's default behavior. Unauthorized actions are hidden in the UI and refused on the server.

## Caching

The published tree of each placement is cached under `menu-builder.{placement}` until the placement is saved again. Only that placement's key is cleared. Visibility, the current page and URLs are resolved per request, so one cache entry serves every visitor. Drafts are never cached as published data.

```php
'cache' => [
    'enabled' => env('MENU_BUILDER_CACHE', true),
    'store' => null,      // cache store, null = default
    'prefix' => 'menu-builder',
    'ttl' => null,        // seconds, null = until the next save
],
```

`Menu::flushCache('header')` clears a placement manually.

## Customizing the model

Extend the model and point the config at it:

```php
'model' => App\Models\MenuItem::class,   // must extend the package model
'table_name' => 'menu_items',
```

## Database schema

The package uses a single `menu_items` table, stored as an adjacency list:

| Column | |
| --- | --- |
| `id`, `parent_id` | `parent_id` references `menu_items.id` with `ON DELETE RESTRICT`: subtrees are only deleted explicitly by the package |
| `placement`, `type` | placement and item type keys |
| `label` | nullable (model-backed items can derive it) |
| `data` | JSON with type-specific data (link target, record id, custom fields) |
| `icon`, `color`, `badge`, `badge_color` | presentation |
| `visibility`, `is_active` | visibility rule key and on/off switch |
| `sort_order`, timestamps | position among siblings |

It has an index on `(placement, parent_id, sort_order)`.

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan level 8 (Larastan)
composer format    # Pint
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [License File](LICENSE.md).
