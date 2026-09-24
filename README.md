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

The item form picks icons with [Filament Icon Hub](https://github.com/syriable/filament-icon-hub), which is installed as a dependency. Publish its field assets once, and again after updates. This is usually already part of your `post-autoload-dump` scripts:

```bash
php artisan filament:assets
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
                ->navigation(true)             // set to false to hide the navigation item
                ->locales(['en', 'ar'])        // optional, overrides config('menu-builder.locales')
                ->slideOver(true)              // optional, overrides config('menu-builder.item_form.slide_over')
                ->modalWidth('2xl'),           // optional, overrides config('menu-builder.item_form.width')
        );
}
```

### The item form

The form that creates and edits items opens as a slide-over or a centered modal. Set this and its width in `config/menu-builder.php`:

```php
'item_form' => [
    'slide_over' => true, // false: a centered modal
    'width' => '2xl',     // xs, sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl, 6xl, 7xl, full or screen
],
```

The plugin methods `slideOver()` and `modalWidth()` (a string or `Filament\Support\Enums\Width`) override these values for one panel.

The form puts the essentials first: type, label, the fields of the type (URL, route, button settings), visibility and status. Collapsible sections below hold the secondary options: **Label translations**, **Appearance** (icon, color, badge), **Text** and **HTML attributes**. A section starts collapsed unless the item already has values in it, and fields sit two per row wherever they fit. Fields of a [custom item type](#custom-item-types) go in a two-column grid, so call `->columnSpanFull()` on the wide ones.

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

### HTTP methods (POST, PUT, PATCH, DELETE)

Links and buttons have an **HTTP method**: GET (default), POST, PUT, PATCH or DELETE. A GET item is a normal link. Any other method renders the item as a submit button inside a small form, with the CSRF token and, for PUT, PATCH and DELETE, Laravel's `_method` field. It looks exactly like a link or button of the same kind. This is how a **Logout** item works:

```text
Type:         Link
Link to:      Route
HTTP method:  POST
Route:        logout
```

```html
<form class="mb-form" method="POST" action="https://example.com/logout">
    <input type="hidden" name="_token" value="…">
    <button type="submit" class="fi-link … mb-item mb-item-link">Logout</button>
</form>
```

- The route picker only offers routes that accept the chosen method. Saving rejects a route that doesn't accept it, for example a GET link to a POST-only route.
- The form uses `display: contents`, so the item keeps its place in the layout.
- These items are never marked as the current page, and a dropdown or mega panel doesn't repeat them as a "view all" entry.
- `ResolvedMenuItem::$method` (an `HttpMethod` enum) and `usesForm()` are available to custom item components.

In a seeder:

```php
['type' => 'link', 'label' => 'Logout', 'visibility' => 'authenticated', 'data' => [
    'link_type' => 'route',
    'route' => 'logout',
    'method' => 'POST',
]],
['type' => 'button', 'label' => 'Delete account', 'color' => 'danger', 'data' => [
    'url' => '/account',
    'method' => 'DELETE',
]],
```

### Dynamic URLs and route parameters

URLs and route parameter values can contain placeholders. They are resolved for each visitor every time the menu is built:

| Placeholder | Value |
| --- | --- |
| `{user}` | the signed-in user's route key (usually the ID) |
| `{user.username}` | an attribute of the signed-in user (hidden attributes such as `password` are never used) |
| `{route.user}` | a parameter of the current page's route; bound models give their route key |
| `{query.ref}` | a value from the current query string |

For example, a "My profile" item that opens `route('users.show', $user)` for whoever is signed in:

```text
Link to:          Route
Route:            users.show
Route parameters: user = {user}
```

On `/users/42`, a "Follow" item with `user = {route.user}` links to the profile being viewed. Placeholders also work in URLs (`/users/{user}/settings`) and inside longer values (`team-{user.team_id}`). In URLs the values are URL-encoded.

**When a placeholder has no value** (a guest has no `{user}`, the page has no `{route.user}` parameter), the item is left out of the menu. A "My profile" link therefore disappears for guests without any visibility rule. Saving checks that every placeholder in route parameters is known. Unknown placeholders in URLs stay as they are.

Register your own placeholders, for example in `AppServiceProvider::boot()`:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

Menu::registerUrlParameter('team', fn (?Authenticatable $user, ?string $path): ?string => match ($path) {
    'slug' => $user?->currentTeam?->slug,  // {team.slug}
    default => $user?->currentTeam?->id,   // {team}
});

Menu::registerUrlParameter('locale', fn (Request $request): string => app()->getLocale()); // {locale}
```

The resolver may inject `$user`, `$request` and `$path` (the part after the dot) and returns a string, number, enum, routable model, or `null` to hide the link.

In a seeder:

```php
['type' => 'link', 'label' => 'My profile', 'visibility' => 'authenticated', 'data' => [
    'link_type' => 'route',
    'route' => 'users.show',
    'route_parameters' => ['user' => '{user}'],
]],
```

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

## Icons

The **Icon** field of the item form is Icon Hub's `IconSelect`: a searchable dropdown with an icon grid. It covers every installed Blade Icons set (Heroicons ship with Filament; add `lucide`, `tabler`, `fontawesome`, … by installing their Blade Icons packages) and any provider you register with Icon Hub (local SVG folders, uploads, remote APIs).

Icons of Blade Icons sets are saved as their Blade Icons name, for example `heroicon-m-user`. `$item->icon` from `Menu::build()` can therefore go straight into any Filament component or `@svg()` in your own templates:

```blade
<x-filament::link :icon="$item->icon" :href="$item->url">{{ $item->label }}</x-filament::link>
```

Items saved as Icon Hub identifiers (`heroicons:m-user`) are converted when the menu is built, so they work too. Only icons without a Blade Icons name (uploads, local SVG folders, remote APIs) stay `provider:name` identifiers. Render those with `Support\Icons::safe($item->icon)`, which accepts both formats and returns `null` for unknown icons. The package's own components already use it.

In a seeder, use either format:

```php
['type' => 'link', 'label' => 'Profile', 'icon' => 'heroicon-m-user', 'data' => [...]],
```

To limit which icon sets the picker offers, use Icon Hub's config (`config/icon-hub.php`, `blade_icons.sets`).

## Visibility

Every item has a visibility rule. The built-in rules are `everyone`, `guests` and `authenticated`. You can register your own:

```php
use Syriable\Filament\Plugins\MenuBuilder\MenuVisibility;

Menu::registerVisibility(
    MenuVisibility::make('admins', 'Administrators', fn (?Authenticatable $user) => $user?->is_admin === true),
);
```

When an item is invisible or inactive, its whole subtree is hidden. Items with an unknown visibility rule are hidden too, so a missing rule fails closed.


### Screen sizes

The **Screen sizes** section of the item form shows an item only on some screens:

| Goal | Show from | Hide from |
| --- | --- | --- |
| Phones only | – | `md` |
| Tablets and larger | `md` | – |
| Tablets only | `md` | `lg` |
| Desktops only | `xl` | – |

The breakpoints are Tailwind's defaults: `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px and `2xl` 1536px. The rule applies to the whole entry: an item with children disappears together with its dropdown, accordion or column. It works in every variant, including dropdown panels.

The setting is stored in `data.screens`:

```php
['type' => 'link', 'label' => 'Download the app', 'data' => [
    'link_type' => 'url',
    'url' => '/app',
    'screens' => ['until' => 'md'],              // phones only
]],
['type' => 'button', 'label' => 'Sign up', 'data' => [
    'url' => '/register',
    'screens' => ['from' => 'md'],               // tablets and larger
]],
```

It renders as `mb-show-from-{breakpoint}` and `mb-hide-from-{breakpoint}` classes on the item's wrapper, backed by media queries in the package CSS. Hidden entries stay in the HTML, so crawlers and screen readers on other sizes are not affected. A range that can never match (e.g. show from `lg`, hide from `md`) is rejected on save. `ResolvedMenuItem::$screens` exposes the setting (`from`, `until`, `showsAt($width)`) to custom renderers and JavaScript frontends.

## Building menus on the frontend

```php
use Syriable\Filament\Plugins\MenuBuilder\Facades\Menu;

$items = Menu::build('header');                  // current user and current URL
$items = Menu::build('header', $user);           // a specific user
$items = Menu::build('header', currentUrl: $url);
$items = Menu::build('header', locale: 'ar');   // labels in another locale (default: app()->getLocale())
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

The package ships a small set of anonymous Blade components built on Filament's own UI components. Pass them the result of `Menu::build()` and they render the whole tree: hierarchy, dropdowns, links, buttons, headings, attributes, icons, badges and the active state. You never loop over the tree yourself.

```blade
<x-menu-builder::menu :items="Menu::build('header')" label="Main navigation" />
```

| Prop | Default | |
| --- | --- | --- |
| `items` | `[]` | the collection returned by `Menu::build()` |
| `variant` | `dropdown` | `dropdown`: horizontal; items with children open Filament dropdowns, nested at any depth<br>`tree`: vertical accordion (sidebar, mobile drawer); the active trail starts open<br>`columns`: root items as columns with their children listed below them (footer)<br>`mega`: a scrollable row of categories whose groups open in wide panels on hover (see [Mega menu](#mega-menu)) |
| `direction` | locale | `ltr` or `rtl`; defaults to Filament's direction for the current locale |
| `item-component` | `menu-builder::item` | component that renders one item's element |
| `heading-tag` | `span` | element used for headings |
| `label` | – | `aria-label` of the `<nav>` |
| `item-class` | – | classes added to every item element (link, heading, button, dropdown entry) |
| `active-class` | – | classes added to the current item and its ancestors |
| `dropdown-class` | – | classes added to the content of every dropdown panel |
| `with-assets` | config | print the layout CSS and accordion script (once per page) |
| `panel-class`, `open-delay`, `close-delay`, `panel-breakpoint` | – / config | `mega` variant only, see [Mega menu](#mega-menu) |

Thin wrappers exist for common placements. Each one is `menu` with a variant and a class, and accepts the same props:

```blade
<x-menu-builder::header :items="Menu::build('header')" />   {{-- dropdown --}}
<x-menu-builder::sidebar :items="Menu::build('sidebar')" /> {{-- tree --}}
<x-menu-builder::footer :items="Menu::build('footer')" />   {{-- columns --}}
<x-menu-builder::mega :items="Menu::build('categories')" /> {{-- mega --}}
```

### Which Filament component renders what

The renderer picks the component from the item type, its position and whether it has children:

| Item | Top level, tree and columns | Inside a dropdown panel |
| --- | --- | --- |
| Link | [`<x-filament::link>`](https://filamentphp.com/docs/5.x/components/link) (`<a>`) | `<x-filament::dropdown.list.item tag="a">` |
| Heading | `<x-filament::link>` as `heading-tag`, semibold | `<x-filament::dropdown.header>` |
| Button | [`<x-filament::button>`](https://filamentphp.com/docs/5.x/components/button) | `<x-filament::button>`, full width |
| Item with children (`dropdown` variant) | [`<x-filament::dropdown>`](https://filamentphp.com/docs/5.x/components/dropdown) with the item as trigger and a chevron | a nested `<x-filament::dropdown>` whose trigger is a list item |
| Item with children (`tree` variant) | the item plus an [`<x-filament::icon-button>`](https://filamentphp.com/docs/5.x/components/icon-button) toggle | – |
| Badge | [`<x-filament::badge>`](https://filamentphp.com/docs/5.x/components/badge) (`start`/`end`) or the component's own badge (`top`) | the list item's badge |

Other rules the renderer follows:

- **Colors.** Links, headings and dropdown triggers without a color of their own inherit the text color of the menu (see [Colors and hover](#colors-and-hover)), so a menu in a dark header is white when the header is. An item with a color (chosen in the form) uses that Filament color. Buttons keep Filament's button look and default to `primary`.
- **Icons.** An icon that is not installed is skipped instead of throwing, so a removed icon set never breaks the page.
- **Parent links.** A link with children becomes a dropdown trigger. Its own page is listed as the first entry of the panel so it stays reachable.
- **Accessibility.** Current pages get `aria-current="page"`, new tabs `target="_blank" rel="noopener noreferrer"`, and accordion toggles get `aria-expanded`, `aria-controls` and a translated label.

### Structure

```html
<nav class="mb-menu mb-menu--dropdown" data-mb-menu="dropdown">
  <ul class="mb-list mb-root" data-level="1">
    <li class="mb-entry">                                 <!-- wrapper -->
      <div class="mb-row">
        <a class="fi-link … mb-item mb-item-link" href="/">…</a>     <!-- item root -->
      </div>
    </li>
    <li class="mb-entry mb-has-children">
      <div class="fi-dropdown mb-dropdown" data-level="1" x-data="filamentDropdown">
        <div class="fi-dropdown-trigger"><button class="fi-link … mb-trigger">…</button></div>
        <div class="fi-dropdown-panel">…</div>
      </div>
    </li>
  </ul>
</nav>
```

### Dropdowns and nested menus

An item with children is automatically a dropdown, and a child with children is a nested dropdown, at any depth. There is no separate "dropdown" item type.

- The first level opens below its trigger. Deeper levels open to the *inline end*: right in LTR, left in RTL.
- Dropdowns are Filament's: they open on click or with `Enter`/`Space`, close on click outside or `Escape`, and use Floating UI to flip and shift so a panel never leaves the viewport.
- The chevron follows the open state: on the first level it turns to point up while the panel is open, and on nested levels it nudges towards its panel (mirrored in RTL). The animation is skipped when the visitor prefers reduced motion.
- For mobile navigation use the `tree` variant (or `<x-menu-builder::sidebar>`), an accordion toggled with icon buttons.

The package's own assets are tiny: layout CSS and a script for the accordion toggles, printed once per page. Set `menu-builder.frontend.assets` to `false` (or pass `:with-assets="false"`) to ship your own. The inline tags use Laravel's Vite CSP nonce when one is set.

### Mega menu

The `mega` variant renders a marketplace-style category bar: a horizontal row of categories that scrolls when it is wider than the page, and a wide panel per category that opens on hover with the category's groups laid out in columns.

```text
Design   Programming   Marketing   Video   Writing   …   ›
┌──────────────────────────────────────────────────────────┐
│ Logo & Brand          Web Design          Print          │
│ Logo Design           Landing Pages       Flyers         │
│ Brand Style Guides    Website Redesign    Posters        │
└──────────────────────────────────────────────────────────┘
```

#### Registering the placement

The variant needs a three-level tree: categories, groups and links. `MegaMenu::register()` adds a placement with exactly these rules, plus the **Mega menu category** item type. Call it from a service provider:

```php
use Syriable\Filament\Plugins\MenuBuilder\MegaMenu;

public function boot(): void
{
    MegaMenu::register(); // placement "categories"
}
```

| Level | Item types | |
| --- | --- | --- |
| 1 | `mega-category` | a link (URL or named route) with a **Panel columns** setting |
| 2 | `heading` or `link` | a group title; a `link` makes the title clickable |
| 3 | `link` | the links of a group |

The tree guard enforces these rules in the editor, for drag & drop, in `Menu::sync()` and in the actions. Nothing is registered until you call `register()`, so the item type never shows up in your other placements.

Pass another key to register more placements, and a closure to adjust the preset:

```php
MegaMenu::register('services', fn (MenuPlacement $placement) => $placement
    ->label('Service categories')
    ->childItemTypes('heading', ['link', 'category']));   // also allow a model-backed type in groups
```

`MegaMenu::placement()` returns the preset without registering it, and `MegaMenu::itemType()` the item type.

#### Columns

Each category chooses **1 to 4** panel columns in its form. Left on **Automatic**, the panel gets one column per group, up to four. Groups never break across columns. The value is stored as `data.columns`:

```php
Menu::sync('categories', [
    ['type' => 'mega-category', 'label' => 'Design', 'data' => [
        'link_type' => 'url', 'url' => '/design', 'columns' => 3,
    ], 'children' => [
        ['type' => 'heading', 'label' => 'Logo & Brand', 'children' => [
            ['type' => 'link', 'label' => 'Logo Design', 'badge' => 'New', 'data' => ['link_type' => 'url', 'url' => '/design/logo']],
        ]],
        ['type' => 'link', 'label' => 'Web Design', 'data' => ['link_type' => 'url', 'url' => '/design/web'], 'children' => [
            ['type' => 'link', 'label' => 'Landing Pages', 'data' => ['link_type' => 'url', 'url' => '/design/web/landing']],
        ]],
    ]],
]);
```

Badges, icons, colors, text options and HTML attributes work on every level as usual. A link with a color and an icon makes a highlighted "spotlight" entry.

#### Rendering

```blade
<x-menu-builder::mega :items="Menu::build('categories')" label="Categories" />
```

It accepts the props of `menu`, plus:

| Prop | Default | |
| --- | --- | --- |
| `panel-class` | – | classes added to every panel |
| `open-delay` | `menu-builder.mega.open_delay` (100) | milliseconds before a panel opens on hover |
| `close-delay` | `menu-builder.mega.close_delay` (150) | milliseconds before a panel closes after the pointer leaves |
| `panel-breakpoint` | `menu-builder.mega.breakpoint` (1160) | minimum viewport width in pixels for panels |

The script is an Alpine component that Filament loads on demand with `x-load`, so the page needs `@filamentScripts` (see [Filament on your frontend](#filament-on-your-frontend)) and the published Filament assets (`php artisan filament:assets`).

#### Behavior

- **Scrolling.** When the categories do not fit, an arrow appears on each side that has more of them. A click scrolls by most of the visible width, and touch and trackpad scrolling work too. Categories that are cut off ignore the pointer, so a panel never opens from under an arrow. Keyboard focus scrolls a category into view.
- **Panels.** A panel is aligned with the start of its category and may extend past the menu. It is moved back only when it would leave the viewport, and keeps `--mb-mega-panel-viewport-gap` from its edges. Scrolling the row closes the open panel.
- **Screen sizes.** Below `panel-breakpoint` the row still scrolls, and a category is a plain link. For phones, render the same placement with `<x-menu-builder::sidebar>` (the `tree` variant).
- **Touch.** On a touch screen above the breakpoint, the first tap opens the panel and the second tap follows the link. The panel then starts with an "All of …" link to the category page.
- **Keyboard.** `Tab` moves through the categories, `ArrowDown` opens the panel of the focused category and focuses its first link, `Escape` closes it and returns focus, and tabbing out of a panel closes it. Categories get `aria-expanded` and `aria-controls`.
- **Direction.** Everything is mirrored in RTL: arrows, alignment and scrolling. The `<nav>` gets a `dir` attribute from the `direction` prop.
- **`wire:navigate`.** When the menu is kept across pages with `@persist`, the current page and active trail are updated from the new URL. Classes from `active-class` are rendered on the server only.

#### Events

The `<nav>` dispatches `mb-mega-open` and `mb-mega-close`, with the category's menu item id in `detail.id`:

```blade
<div x-on:mb-mega-open="analytics.track('category_menu_open', { id: $event.detail.id })">
    <x-menu-builder::mega :items="Menu::build('categories')" />
</div>
```

#### Styling

The panel width follows from its columns, so it is known before the panel opens. Adjust it and the rest of the look with custom properties on the menu or any ancestor:

| Property | Default | |
| --- | --- | --- |
| `--mb-mega-column-width` | `15.5rem` | width of one panel column |
| `--mb-mega-column-gap` | `4.5rem` | space between panel columns |
| `--mb-mega-panel-padding-block`, `--mb-mega-panel-padding-inline` | `1.25rem`, `2rem` | panel padding |
| `--mb-mega-panel-background`, `--mb-mega-panel-border-color`, `--mb-mega-panel-border-width` | white / gray-900 in dark mode | panel surface |
| `--mb-mega-panel-shadow`, `--mb-mega-panel-z-index` | subtle, `40` | panel elevation |
| `--mb-mega-panel-viewport-gap` | `16px` | minimum space between a panel and the viewport edges |
| `--mb-mega-item-gap` | `1.25rem` | minimum space between categories |
| `--mb-mega-strip-padding-block` | `0.625rem` | height of the category row |
| `--mb-mega-indicator-color`, `--mb-mega-indicator-size` | primary, `3px` | line under the hovered, open or active category |
| `--mb-mega-arrow-size` | `2.25rem` | width of the scroll arrows |
| `--mb-mega-fade-color` | white / gray-800 in dark mode | background the arrows fade from; set it to your header's background |

```css
.site-header .mb-menu--mega {
    --mb-mega-fade-color: var(--color-gray-50);
    --mb-mega-indicator-color: var(--color-emerald-500);
}
```

Panel entries use the dropdown colors (`--mb-dropdown-color` and friends), not the menu's `--mb-color`, so a white category row in a dark header still gets readable panels.

The `<nav>` is the containing block of the panels. Don't give the scrolling row (`.mb-mega-strip`), its list or its entries a `position`, or the panels will be clipped. Panels can extend past the `<nav>`, so an ancestor with `overflow: hidden` clips them too.

### Filament on your frontend

Inside a Filament panel everything is styled and scripted already. On your public site, load Filament's styles and scripts once, the same way Filament's own `filament:install --scaffold` does:

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
    @livewireScripts {{-- Alpine, used by the dropdowns --}}
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

### RTL and LTR

Direction comes from the `direction` prop, or from Filament's translation for the current locale (`ar`, `he`, `fa`, … are RTL). It decides which side nested dropdowns open on and which chevron triggers show. The package CSS only uses logical properties, so badges `start` and `end` and the accordion indentation follow the reading direction. Nothing in your data is direction-specific.

```blade
<html dir="{{ __('filament-panels::layout.direction') }}">
…
<x-menu-builder::header :items="Menu::build('header')" direction="rtl" />
```

### Colors and hover

Links and headings look like plain links: the color of their parent, an underline on hover, and no hover background, including inside dropdown panels. Change the colors in whichever way suits your project:

**1. Set the text color on the menu or any ancestor.** Items inherit it.

```blade
<header class="bg-gray-900 text-white">
    <x-menu-builder::header :items="Menu::build('header')" />
</header>
```

**2. Add classes to the items.** Tailwind utilities always win over the package styles.

```blade
<x-menu-builder::header
    :items="Menu::build('header')"
    item-class="text-white hover:text-amber-300"
    active-class="text-amber-400 font-semibold"
    dropdown-class="bg-gray-900 text-gray-100"
/>
```

**3. Use the custom properties**, for example in your CSS or a `style` attribute:

| Property | Default | |
| --- | --- | --- |
| `--mb-color` | inherited | links and headings |
| `--mb-hover-color` | `--mb-color` | links on hover and focus |
| `--mb-active-color` | `--mb-color` | the current page and its ancestors |
| `--mb-dropdown-color` | Filament gray | entries in dropdown panels |
| `--mb-dropdown-hover-color` | inherited | dropdown entries on hover |
| `--mb-dropdown-active-color` | inherited | the current page in dropdown panels |
| `--mb-gap` | `1.25rem` | space between root items |
| `--mb-submenu-indent` | `1rem` | accordion indentation |

```css
.site-header .mb-menu {
    --mb-color: white;
    --mb-hover-color: var(--color-amber-300);
    --mb-active-color: var(--color-amber-400);
}
```

Every item element also has the classes `mb-item`, `mb-item-{link|heading|button}` and, when it is the current page or an ancestor of it, `mb-active`.

### Text options per item

The **Text** section of the item form styles one item without any CSS:

| Option | Values | Class |
| --- | --- | --- |
| Weight | light, normal, medium, semibold, bold, extra bold | `mb-weight-*` |
| Size | extra small, small, normal, large, extra large | `mb-text-{xs,sm,base,lg,xl}` |
| Italic | on or off | `mb-italic` |
| Underline | always, on hover, never | `mb-underline-{always,hover,none}` |
| Letter case | UPPERCASE, lowercase, Capitalize | `mb-transform-*` |
| Cursor | hand (pointer), arrow | `mb-cursor-{pointer,default}` |
| Hover color | any hex, `rgb()`, `hsl()` or `oklch()` color | `mb-hover-color` + `--mb-item-hover-color` |

"Default" keeps the look of the item type. Links underline on hover and headings don't. The options are stored in `data.text_style`, validated on save, and ignored when invalid, so they can't inject CSS. They apply to the item element everywhere: top level, dropdown entries, triggers and buttons. `ResolvedMenuItem::$textStyle` exposes them to custom components, and `itemAttributes()` already includes the classes and the style.

### Styling

The components carry Filament's classes (`fi-link`, `fi-btn`, `fi-dropdown-panel`, `fi-badge`, …), so your Filament theme applies to them. The package's layout CSS sits in the `base` cascade layer inside `:where()`; the plain link look sits in the `components` layer. Tailwind utilities (the `utilities` layer) and any class in your theme override both.

### Placement-specific components

Build your own wrappers and keep the package responsible for the tree. For example, `resources/views/components/app-footer-menu.blade.php`:

```blade
@props(['items'])

<x-menu-builder::menu :items="$items" variant="columns" class="text-sm" />
```

```blade
<x-app-footer-menu :items="Menu::build('footer')" />
```

### Custom item markup

There are two ways to change how items render:

1. **Publish and edit the package views** (`components/item.blade.php`, `dropdown.blade.php`, `dropdown-item.blade.php`, …):

   ```bash
   php artisan vendor:publish --tag="menu-builder-views"
   # resources/views/vendor/menu-builder/components/item.blade.php
   ```

2. **Pass your own component** for one menu only. It receives `item`, `level`, `heading-tag` and, for dropdown triggers, `trigger`. It renders top-level items, accordion items and the leaf entries of dropdown panels; the package still renders the wrappers, dropdowns and toggles around it:

   ```blade
   <x-menu-builder::menu :items="$items" item-component="nav-item" />
   ```

   ```blade
   {{-- resources/views/components/nav-item.blade.php --}}
   @props(['item', 'level' => 1, 'headingTag' => 'span', 'trigger' => false])

   <x-filament::link
       :tag="$trigger ? 'button' : 'a'"
       :href="$trigger ? null : $item->url"
       :attributes="$item->itemAttributes()->class('nav-link')"
   >{{ $item->label }}</x-filament::link>
   ```

Use `$item->itemAttributes()` in custom components. It returns an escaped attribute bag, so administrator-entered values are always safe to print, also when passed to Filament components via `:attributes`.

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

Buttons need Filament's styles on your frontend; see [Filament on your frontend](#filament-on-your-frontend).

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
| `top` | Filament's corner badge of the link or button component |

## Translating labels

List the locales of your site in `config/menu-builder.php` and the item form shows a label field for each one:

```php
'locales' => [
    'en' => 'English',
    'ar' => 'العربية',
],
// or simply: 'locales' => ['en', 'ar'],
```

`MenuBuilderPlugin::make()->locales([...])` overrides the config for one panel.

The main **Label** is the default. A locale left empty uses it. The translations are stored in the item's `data` (`data.label_translations`), so no migration is needed.

On the frontend, `Menu::build()` uses the label of `app()->getLocale()`, falling back from a regional locale to its language (`ar_SA` → `ar`) and then to the default label. Pass `locale:` to build another language:

```php
Menu::build('header');               // current locale
Menu::build('header', locale: 'ar'); // Arabic labels
```

When seeding:

```php
['type' => 'link', 'label' => 'Pricing', 'data' => [
    'link_type' => 'url',
    'url' => '/pricing',
    'label_translations' => ['ar' => 'الأسعار', 'de' => 'Preise'],
]]
```

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
node --test 'tests/js/*.test.mjs'   # frontend script (no dependencies)
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [License File](LICENSE.md).
