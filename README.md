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

The `data` keys `attributes`, `attribute_target`, `badge_position` and `render_as` are shared by all item types. Don't use them for custom fields.

A custom type can render as a button too, which is useful for model-backed call-to-action items:

```php
MenuItemType::make('cta')->renderAs(RenderAs::Button)->resolveUrlUsing(fn () => route('register'));
```

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
| `children`, `hasChildren()`, `isDropdown()` | nested `ResolvedMenuItem`s; items with children are dropdowns |
| `renderAs`, `isLink()`, `isButton()`, `isHeading()` | the item's root element: link, [button](#buttons) or heading, from the item type |
| `buttonOption($key, $default)` | button settings: `size`, `outlined`, `icon_position` |
| `attributes`, `attributeTarget` | validated custom HTML attributes and where they go |
| `itemAttributes()`, `wrapperAttributes()` | escaped `ComponentAttributeBag`s for the item element and its `<li>` |
| `badgePosition`, `hasBadge()` | `start`, `end` or `top` |
| `data` | the raw type data, for custom rendering |
| `toArray()` / JSON | for APIs and JavaScript frontends |

## Rendering menus with Blade components

The package ships a small set of anonymous Blade components. Pass them the result of `Menu::build()` and they handle the whole tree: hierarchy, dropdowns, links, buttons, headings, attributes, icons, badges and the active state. You never loop over the tree yourself.

```blade
<x-menu-builder::menu :items="Menu::build('header')" label="Main navigation" />
```

| Prop | Default | |
| --- | --- | --- |
| `items` | `[]` | the collection returned by `Menu::build()` |
| `variant` | `dropdown` | `dropdown`: horizontal, with dropdowns on wide screens and an accordion on small screens<br>`tree`: vertical accordion; the active trail starts open<br>`columns`: root items as columns with their children listed below them |
| `item-component` | `menu-builder::item` | component that renders one item's element |
| `heading-tag` | `span` | element used for headings |
| `label` | – | `aria-label` of the `<nav>` |
| `with-assets` | config | print the structural CSS and dropdown script (once per page) |

Thin wrappers exist for common placements. Each one is `menu` with a variant and a class:

```blade
<x-menu-builder::header :items="Menu::build('header')" />   {{-- dropdown --}}
<x-menu-builder::sidebar :items="Menu::build('sidebar')" /> {{-- tree --}}
<x-menu-builder::footer :items="Menu::build('footer')" />   {{-- columns --}}
```

### Structure

```html
<nav class="mb-menu mb-menu--dropdown" data-mb-menu="dropdown">
  <ul class="mb-list mb-root" data-level="1">
    <li class="mb-entry mb-has-children">          <!-- wrapper -->
      <div class="mb-row">
        <a class="mb-item mb-item-link" href="/services">…</a>   <!-- item root -->
        <button class="mb-toggle" data-mb-toggle aria-expanded="false" aria-controls="…">…</button>
      </div>
      <ul class="mb-list mb-submenu" data-level="2">…</ul>
    </li>
  </ul>
</nav>
```

### Dropdowns and nested menus

An item with children is automatically a dropdown, and a child with children is a nested dropdown, at any depth. There is no separate "dropdown" item type.

- **Wide screens (≥ 48rem):** submenus open on hover, on keyboard focus, or with the toggle button. The first level opens below its parent and deeper levels open to the side. If a submenu would leave the viewport it flips to the other side.
- **Small screens:** the same markup becomes an accordion. Tapping a toggle expands or collapses that item's children in place.
- `Escape` closes the open submenu, and clicking outside closes open dropdowns.

The behavior comes from a dependency-free script of about 100 lines, printed once per page. It needs no Alpine and no build step. Set `menu-builder.frontend.assets` to `false` (or pass `:with-assets="false"`) to ship your own CSS and JS instead. The inline tags use Laravel's Vite CSP nonce when one is set.

### RTL and LTR

The stylesheet only uses logical properties (`inset-inline-start`, `padding-inline-start`, …), so a menu inside `dir="rtl"` mirrors itself:

- Nested submenus open towards the *inline end*: right in LTR, left in RTL.
- The flip check uses the element's computed direction and flips towards the *inline start*.
- Badge `start` and `end` follow the reading direction.

Nothing in your data is direction-specific.

### Styling

All package selectors are wrapped in `:where()`, so they have zero specificity and a single class in your theme overrides them. Common values are custom properties:

```css
.site-header .mb-menu {
    --mb-submenu-background: #111827;
    --mb-submenu-min-width: 14rem;
    --mb-badge-background: #fde68a;
}
```

### Placement-specific components

Build your own wrappers and keep the package responsible for the tree. For example, `resources/views/components/app-footer-menu.blade.php`:

```blade
@props(['items'])

<x-menu-builder::menu :items="$items" variant="columns" class="grid-cols-4 gap-8 text-sm" />
```

```blade
<x-app-footer-menu :items="Menu::build('footer')" />
```

### Custom item markup

There are two ways to change how a single item renders:

1. **Publish and edit the package views.** Laravel then uses your copy of `components/item.blade.php`:

   ```bash
   php artisan vendor:publish --tag="menu-builder-views"
   # resources/views/vendor/menu-builder/components/item.blade.php
   ```

2. **Pass your own component** for one menu only. It receives `item`, `level` and `heading-tag`, and the package still renders the wrappers, dropdowns and toggles around it:

   ```blade
   <x-menu-builder::menu :items="$items" item-component="nav-item" />
   ```

   ```blade
   {{-- resources/views/components/nav-item.blade.php --}}
   @props(['item', 'level' => 1, 'headingTag' => 'span'])

   <a {{ $item->itemAttributes()->class('nav-link')->merge(['href' => $item->url]) }}>{{ $item->label }}</a>
   ```

Use `$item->itemAttributes()` in custom components. It returns an escaped attribute bag, so administrator-entered values are always safe to print.

## Buttons

**Button** is a built-in item type, next to **Heading** and **Link**. Choose it when you create an item:

```text
Type: Heading | Link | Button
```

Buttons render with Filament's own button component, [`<x-filament::button>`](https://filamentphp.com/docs/5.x/components/button), and the item form controls it:

| Field | Maps to |
| --- | --- |
| Label | the button text |
| Color (Appearance) | `color`: primary, gray, info, success, warning or danger (default primary) |
| Size | `size`: XS, S, M (default), L or XL |
| Outlined | `outlined` |
| Icon (Appearance) + Icon position | `icon` and `icon-position` (before or after) |
| Badge + Badge position | Filament's corner badge for *top*; inline for *start* and *end* |
| URL (optional) | empty gives `<button type="button">`; filled gives a link styled as a button |
| HTML attributes | added to the button element |

A button without a URL does nothing by itself. Your application decides what it does through the item's HTML attributes, for example opening a modal, a drawer or search, or triggering Alpine or Livewire:

```text
Type:       Button
Label:      Login
Attributes: x-on:click = $dispatch('open-modal', { id: 'login' })
            data-modal = login
```

```html
<button type="button" class="fi-btn fi-color fi-color-primary fi-size-md … mb-item mb-item-button"
        x-on:click="$dispatch('open-modal', { id: 'login' })" data-modal="login">Login</button>
```

In code:

```php
['type' => 'button', 'label' => 'Login', 'color' => 'gray', 'icon' => 'heroicon-o-user', 'data' => [
    'size' => 'sm',
    'outlined' => true,
    'attributes' => ['x-on:click' => "\$dispatch('open-modal', { id: 'login' })"],
]]
```

### Filament styles on your frontend

Inside a Filament panel, buttons are styled automatically. On your public site, load Filament's styles once, the same way Filament's own `filament:install --scaffold` does:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/filament/support/resources/css/index.css';
```

```blade
<head>
    @filamentStyles
    @vite('resources/css/app.css')
</head>
<body>
    …
    @filamentScripts
</body>
```

Filament registers its colors when a *panel* serves a request, so on non-panel pages register them yourself, e.g. in `AppServiceProvider::boot()`:

```php
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

FilamentColor::register([
    'primary' => Color::Amber,
    'gray' => Color::Zinc,
    'info' => Color::Blue,
    'success' => Color::Green,
    'warning' => Color::Orange,
    'danger' => Color::Red,
]);
```

## HTML attributes

Every item, whatever its type (Heading, Link, Button or a custom type), can carry any HTML attributes. You edit them as key/value pairs in the **HTML attributes** section of the item form. There is no allow-list:

```text
class      = btn btn-primary
id         = open-login
data-modal = login
aria-label = Open login
x-on:click = open = true
wire:click = openLogin
```

- **Storage:** attributes live in the existing `data` JSON column under `data.attributes`, so adding a new attribute never needs a migration.
- **Target:** **Apply attributes to** chooses the item element (`<a>`, the Filament button or the heading; the default) or its wrapper (`<li>`). Children never inherit them.
- **Merging:** `class` is appended to the package classes. Any other attribute overrides the default, for example `type="submit"` or `rel`.
- **Empty values** render as bare attributes (`data-flag`). The HTML boolean attributes (`disabled`, `hidden`, `required`, `open`, …) are left out when the value is `false`, `0`, `off` or `no`. Other attributes keep their text, so `aria-expanded="false"` stays as it is.
- **Safety:** attribute names are validated on every write path. Anything that could break out of the attribute syntax (whitespace, quotes, `<`, `>`, `/` or `=`) is rejected, and the renderer drops such names again as a second line of defense. Values are always HTML-escaped.

Attributes are a trusted-administrator feature. They let editors attach frontend behavior (`x-on:*`, `wire:*`) to menu items, so give edit access only to people you would trust with that.

## Badges

A badge (text plus an optional color) can sit in three logical positions, chosen with **Badge position**:

| Position | Placement |
| --- | --- |
| `start` | before the label: left in LTR, right in RTL |
| `end` (default) | after the label: right in LTR, left in RTL |
| `top` | raised above the end edge of the label, so it stays attached to the text whatever its length or direction |

## Editing the tree

Drag an item by its handle and drop it on another row:

- the **top or bottom quarter** of a row places it before or after that row,
- the **middle** places it inside that row, as its last child,
- **dropping below the last child of a parent with the pointer in the indentation gutter** (to the left in LTR, to the right in RTL) moves the item out to the parent's level. Move further into the gutter to go up several levels. The drop line shows the target level.
- the **"Drop here to move to the top level"** zone under the tree moves any item, however deeply nested, to the end of the root level.

Each drop is one validated server call. Moves that break the placement rules are rejected with a message, and nothing is published until you save.

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
| `data` | JSON with type-specific data (link target, record id, custom fields) and the shared rendering options `render_as`, `attributes`, `attribute_target` and `badge_position` |
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
