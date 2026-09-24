# Architecture

This document explains how the package is built and why. Read it before changing the tree engine or adding extension points.

## Principles

- **Generic in configuration, opinionated in implementation.** The host application defines placements, item types, models and visibility rules. The package has exactly one implementation of the tree, the draft, validation, publishing and URL resolution.
- **The server is authoritative.** JavaScript only reports interactions. Every change is validated on the server against the placement rules.
- **Small surface.** Extension points exist only where applications actually differ: placements, item types, visibility rules, the draft store, the model and authorization.

## Package structure

```
config/menu-builder.php              model, placements, cache, drafts, route picker
database/migrations/…stub            menu_items table
resources/dist/                      editor: Alpine component (drag & drop) + stylesheet; mega menu Alpine component
resources/dist/frontend/             frontend: structural menu.css + dependency-free menu.js; mega.css
resources/views/components/          frontend Blade components: menu, items, item, badge, header, footer, sidebar, mega (+ mega/*)
resources/views/filament/            Filament pages and the recursive editor tree partial
resources/lang/en/                   translations
src/
├── MenuBuilderServiceProvider.php   bindings, assets, install command
├── MenuBuilderPlugin.php            Filament plugin (pages + navigation options)
├── Facades/Menu.php                 → MenuManager
├── MenuManager.php                  register*, build, tree, sync, flushCache
├── MenuRegistry.php                 placements, item types, visibility rules
├── MenuPlacement.php                placement definition + structural rules
├── MenuItemType.php                 item type definition (form, rules, resolvers)
├── MenuVisibility.php               named visibility rule
├── MegaMenu.php                     opt-in registration of the mega placement preset and item type
├── MenuBuilder.php                  published tree → ResolvedMenuItem[]
├── ItemTypes/                       built-in heading, link and button types; MegaCategoryType (opt-in)
├── Tree/
│   ├── MenuNode.php                 immutable item attributes
│   ├── MenuTree.php                 in-memory adjacency list (the one tree implementation)
│   ├── MenuTreeGuard.php            the single source of truth for rules
│   ├── MenuEditor.php               apply one change + validate
│   ├── DropPosition.php             before / after / inside
│   └── TreeViolation.php
├── Actions/                         PublishMenuTree + Create/Update/Move/Reorder/Delete
├── Drafts/                          MenuDraft, MenuDraftManager, CacheDraftStore
├── Contracts/DraftStore.php
├── Support/                         MenuRepository (+ cache), UrlResolver, VisibilityResolver, MenuAuthorizer,
│                                    HtmlAttributes (validate/normalize/escape), FrontendAssets
├── Rules/ResolvableRoute.php
├── Data/ResolvedMenuItem.php        frontend DTO (the rendering contract)
├── Enums/                           RenderAs, AttributeTarget, BadgePosition, MegaColumns
├── Events/MenuPublished.php
├── Exceptions/
├── Filament/Pages/                  MenuPlacements (landing), ManageMenu (editor)
└── Models/MenuItem.php
```

## Data flow

```
                ┌──────────── Filament editor ────────────┐
 drag & drop ─► ManageMenu::moveItem ─┐                   │
 forms       ─► create/edit/delete ───┼─► MenuEditor ─► MenuTreeGuard
                                      │        │
                                      │   draft MenuTree (DraftStore)
                                      │        │ Save changes
 seeders / code ─► Actions ───────────┴─► PublishMenuTree ─► DB transaction ─► cache forget ─► MenuPublished
                                                                    │
 frontend ─► Menu::build() ─► MenuRepository::published (cache) ─► MenuBuilder ─► ResolvedMenuItem[]
                                                                                        │
                                          <x-menu-builder::menu> ─► items (recursive) ─► item (a | button | heading) ─► HTML
```

The renderer never touches the database, and the builder never produces HTML.

## Tree engine

`MenuTree` is an adjacency list indexed three ways: nodes by key, parent by key, and ordered children by parent key. This gives:

- `fromModels()`: rows are grouped by `parent_id` once and walked once, O(n).
- `move()` / `moveRelativeTo()`: reject moving a node below itself or its descendants (cycle prevention is a structural invariant of the tree).
- `remove()`: removes a complete subtree.
- `toArray()` / `fromArray()`: a depth-first serialization used by the cache and the draft store. `fingerprint()` hashes it for dirty checks and concurrency checks.

Node keys are `item-{id}` for persisted items and `new-{ulid}` for unsaved ones. Numeric strings are avoided on purpose, because PHP turns them into integer array keys.

## Validation

`MenuTreeGuard` validates:

| Rule | Source |
| --- | --- |
| placement registered | registry |
| type registered | registry |
| type allowed in the placement | `MenuPlacement::itemTypes` |
| type allowed at the root | `MenuPlacement::rootItemTypes` |
| type allowed below its parent | `MenuPlacement::childItemTypes`, `MenuItemType::canHaveChildren` |
| maximum depth | `MenuPlacement::maxDepth` |
| every node reachable (no detached or cyclic nodes) | structure |
| label, icon, colors, badge, visibility | common attributes |
| type data (URL, route + parameters, record, custom fields) | `MenuItemType::rules` |

Structural rules are always checked for the whole tree, in O(n). Data rules are checked for the changed item while editing, and for every item on publish. `MenuEditor` applies each change to a copy and commits it only when the guard accepts it. Both the editor and the actions use it, so there is no second validation path.

Sort order is not validated because it is derived. `PublishMenuTree` writes `0..n-1` for every sibling group from the tree's order, so gaps and duplicates cannot occur.

## Publishing

`PublishMenuTree::handle($tree, $expectedFingerprint)`:

1. Validate the complete tree. Nothing is written if validation fails.
2. Start a transaction and lock the placement's rows.
3. If a fingerprint was given and the persisted tree no longer matches it, throw `StaleMenu` (optimistic concurrency).
4. Walk the tree depth-first so parents are written before their children. Insert new items, and update only the rows that changed.
5. Delete the rows that are missing from the tree, deepest first. The foreign key is `RESTRICT`, so the database never cascades a delete.
6. Commit, clear the placement's cache key and dispatch `MenuPublished`.

The single-item actions (`CreateMenuItem`, `MoveMenuItem`, and so on) load the tree, apply one `MenuEditor` change and publish it, all inside one transaction.

## Drafts

The draft is a `MenuTree` plus the fingerprint of the persisted tree it started from. It lives in a `DraftStore`. The default store is Laravel's cache (`CacheDraftStore`) because:

- It works on shared hosting (file or database cache) and survives Livewire requests.
- The browser only holds a locked draft id, so Livewire payloads stay tiny even for hundreds of items, and clients cannot tamper with the tree.
- Several administrators, or several tabs, each get their own draft id and never share state.

Persistent drafts or revisions only need a different `DraftStore` binding. The editor does not know how drafts are stored.

## Drag & drop

`resources/dist/menu-builder-tree.js` is a small Alpine component loaded on demand with Filament's `x-load`:

- A row becomes `draggable` only while its handle is pressed, so text selection and buttons keep working normally.
- On `dragover`, the pointer position decides the drop position: the top quarter of a row means *before*, the bottom quarter means *after*, and the middle means *inside*. An outline or line shows it. Dropping onto the dragged item or its own subtree is not offered.
- **Moving out of a parent.** When dropping *after* the last child of a parent (ignoring the dragged item), a pointer in the indentation gutter (inline start of the row, so RTL works too) walks up to the ancestor at that level and targets `after(ancestor)`. The drop line extends to the target level through a `--mb-drop-indent` custom property. In the gutter the pointer is not above any row, so the row is resolved by vertical position.
- **Root drop zone.** Shown only while dragging, it targets `after(last root item)`. Both mechanisms reuse the existing `moveItem(key, target, 'after')` call, so the server API, validation and draft handling are unchanged.
- Only a completed drop reaches the server, as one semantic call: `moveItem(key, targetKey, 'before'|'after'|'inside')`. The drop event recomputes the position itself, so it never relies on a highlight left over from an earlier event. The server validates the call and re-renders the tree. There is no per-mousemove traffic, and no tree state is kept in JavaScript.
- **Tree rows carry no Alpine directives.** Livewire's morph can move rows after a reorder and tear down the Alpine bindings of a moved element, so the drag and click listeners live on the root element and find their row through `closest('.mb-row')` and `data-key`. Collapsed items are hidden by a generated `<style wire:ignore>` block of `[data-key]` selectors. Livewire's own `wire:click` on the row buttons is not affected.
- Expand and collapse state is saved in `localStorage` under a key per placement (`menu-builder.{placement}.collapsed`).
- While the draft is dirty, `beforeunload` and `livewire:navigate` ask for confirmation.

Alpine names on the root are specific (`toggleItem`, `expandAllItems`, and so on) because Filament's section component defines `isCollapsed` and similar properties in enclosing Alpine scopes.

## Mega variant

`variant="mega"` is a marketplace-style category bar. `menu` hands it to `mega.menu` after printing the assets; the other variants never touch it, and no core class depends on the mega classes (an architecture test enforces this).

- **Opt-in data shape.** `MegaMenu::register()` registers `MegaCategoryType` (a `LinkType` with a `columns` field in `data`, so it keeps the URL and route picker) and a placement preset: categories at the root, `heading` or `link` groups below them, `link`s inside the groups, three levels. The tree guard enforces it like any other placement. The type is not registered by default, because placements without type rules would offer it everywhere.
- **Deterministic panel width.** `MegaColumns::for()` reads `data.columns`, or uses one column per group up to four. The panel gets `data-mb-mega-columns`, which sets `--mb-mega-cols`, and its width is computed from that in CSS. The script can therefore open and place a panel in one task without measuring its content first.
- **Containing block outside the scroller.** The `<nav>` is `position: relative`; the strip is `overflow-x: auto` but not positioned. Overflow only clips descendants whose containing block is the scroller or inside it, so the panels (absolute, containing block = `<nav>`) escape the clipping and stay put while the strip scrolls. Their vertical position is their static position below the category row; only `inset-inline-start` is set by the script: aligned with the category, clamped to the viewport minus `--mb-mega-panel-viewport-gap`, so a panel can extend past the `<nav>`. The gap is a registered `@property` of type `<length>`, so the script reads it in pixels whatever unit is used. Floating UI was not used because it anchors a panel to its trigger, and `x-teleport` would break the hover path between a category and its panel.
- **Script.** `menu-builder-mega.js` is an Alpine component loaded with Filament's `x-load`, like the editor's tree. All listeners are delegated to the `<nav>` and the markup only has `data-mb-mega-*` hooks, so custom item components need no Alpine code. The geometry (panel offset, scroll state, obscured items) consists of pure functions attached to the export and tested with `node --test`.
- **No Livewire.** `Menu::build()` is cached and the behavior is client-side, so the variant is a plain Blade component.

## Frontend builder

`MenuBuilder::build()`:

1. Loads the published tree: one query, or none when cached.
2. Walks it once to collect active and visible keys, pruning invisible subtrees.
3. Loads the linked records with one `whereIn` query per model-backed type.
4. Builds `ResolvedMenuItem`s recursively. Labels and URLs are resolved by the item type, link items go through `UrlResolver`, and items whose URL or record cannot be resolved are dropped. Items are marked `isCurrent` or `isActiveTrail`.

## Rendering: attributes, render as and badges

The element an item renders as comes from its **type**: `MenuItemType::renderAs()` (Heading → heading, Link → link, Button → button, custom types → link when they have a URL, otherwise heading). The built-in `ButtonType` stores its Filament button settings (`size`, `outlined`, `icon_position`, optional `url`) in `data`, and uses the item's color and icon. The item component renders it with `<x-filament::button>`.

Presentation options shared by every type are **keys inside the existing `data` JSON** (`attributes`, `attribute_target` and `badge_position`, plus the legacy per-item `render_as` override), defined as `MenuNode::DATA_*` constants. No migration is needed, and existing rows fall back to the defaults.

- **Validation** lives in `MenuTreeGuard::validateItem()` next to the other common attributes. It checks the enum values, `render_as=link` only for types with a URL, attribute names and scalar values. `LinkType` excludes its URL and route rules when an item renders as a button or heading.
- **Normalization** happens in `MenuNode` accessors, which `MenuBuilder` copies into `ResolvedMenuItem`. `HtmlAttributes::normalize()` drops invalid names (defense in depth for rows written around the guard), turns empty values into bare attributes and switches off boolean attributes set to `false`, `0`, `off` or `no`.
- **Escaping.** `ComponentAttributeBag::__toString()` does *not* HTML-escape values; Blade escapes component attributes at compile time, but these attributes only exist at runtime. `HtmlAttributes::bag()` therefore escapes every value, and the components only ever print attributes through `ResolvedMenuItem::itemAttributes()` or `wrapperAttributes()`.
- **Target.** Attributes go on the item element (`<a>`, `<button>` or heading) or on its `<li>` wrapper, never on both, never on structural elements (row, toggle, submenu) and never on children.

## Frontend rendering

The renderer is a thin layer of anonymous components over Filament's UI components (`link`, `button`, `badge`, `dropdown`, `dropdown.list.item`, `dropdown.header`, `icon-button`, `icon`):

| Component | Responsibility |
| --- | --- |
| `menu` | `<nav>`, variant (`dropdown`, `tree` or `columns`), direction, assets |
| `items` | one level: the `<li>` wrapper (plus wrapper attributes); per variant, a dropdown, an accordion row with an icon-button toggle, or a plain row; recursion into children |
| `item` | the item's root element: `<x-filament::link>` (links and headings) or `<x-filament::button>`; with `trigger` it becomes a `<button>` with a chevron (the main override point) |
| `dropdown` | an item with children as `<x-filament::dropdown>`; placement from level and direction; the parent's own page as first entry |
| `dropdown-item` | one panel entry: nested dropdown, dropdown header, Filament button, custom item component or dropdown list item |
| `badge` | inline badges via `<x-filament::badge>` |

`header`, `footer` and `sidebar` are one-line wrappers that pass a variant to `menu`. There is no placement-specific rendering logic. Dropdowns are derived from `hasChildren()` alone, at any depth.

- **Why Filament components.** They bring consistent colors, sizes, dark mode and theming, plus a tested dropdown (Alpine + Floating UI with flip and shift), so the package no longer needs its own positioning and flip logic. The cost is that a frontend must load Filament's CSS and scripts (documented in the README).
- **Icons.** Filament components throw for unknown icons. `Support\Icons::safe()` checks the icon once per request and drops unknown ones, so a menu never breaks the page.
- **Attributes.** Item attributes reach Filament components through `:attributes="$item->itemAttributes()"`. That bag is already escaped and Filament prints it verbatim, so values are escaped exactly once.
- **CSS** (`resources/dist/frontend/menu.css`) does layout (row, accordion, columns) in the `base` layer inside `:where()`. It also gives links, headings and dropdown entries a plain look in the `components` layer: no hover backgrounds, and the menu's text color (`--mb-*` custom properties) unless the item has a color. These rules need more specificity than Filament's (`.mb-menu .mb-item.fi-link:not(.fi-color)`) because they share its layer, and they only win because they come later. Tailwind utilities in the `utilities` layer override all of them, which is how `item-class`, `active-class` and `dropdown-class` work. Those props reach the inner components through `@aware`, so they aren't passed down through every level.
- **Editor CSS** (`resources/dist/menu-builder.css`) is registered as a Filament asset, so `@filamentStyles` also prints it on public pages. Every rule is therefore scoped to `.mb-editor`, the root class of the editor pages. Otherwise editor classes such as `.mb-row` and `.mb-label` would style frontend menus. A test enforces this.
- **JS** (`resources/dist/frontend/menu.js`, no dependencies) only handles the accordion toggles (`data-open`, `aria-expanded`) and `Escape`. Dropdown behavior is Filament's.
- Both files are printed inline once per page (`@once`, with the Vite CSP nonce when set) and can be switched off with `menu-builder.frontend.assets`.

## Text options

`Support\TextStyle` is a readonly value object over `data.text_style`. It validates in two places: `TextStyle::rules()` runs in `MenuTreeGuard` for every write path, and `TextStyle::fromArray()` drops unknown values on read. The hover color only accepts hex, `rgb()`, `hsl()` and `oklch()` notations, so it can't close the declaration it is written into. `ResolvedMenuItem::itemAttributes()` adds the resulting `mb-*` classes and the `--mb-item-hover-color` property, so every renderer (and custom item components) gets them for free, even when the HTML attributes target the wrapper. The matching rules live in the `components` layer of the frontend CSS and are more specific than both Filament's rules and the package defaults.

## HTTP methods

`Enums\HttpMethod` is read from `data.method` of item types with a URL. `ResolvedMenuItem::usesForm()` is true for non-GET items that have a URL. The item views then wrap the element in `frontend.form-open` / `form-close`: a `<form class="mb-form">` with `display: contents`, `@csrf` and, for PUT/PATCH/DELETE, a `_method` field. The element becomes a `type="submit"` button. Filament's own `form` prop was avoided on purpose: it turns on `wire:loading` indicators, which would show up on pages without Livewire. `ResolvableRoute` checks that the route accepts the method, and `UrlResolver::selectableRoutes()` filters the picker by method. Non-GET items are never `isCurrent`.

## Dynamic URLs

`Support\UrlParameters` (a singleton) resolves `{name}` / `{name.path}` placeholders. `UrlResolver` applies it to URLs, URL-encoding the values, and to route parameter values, where `route()` encodes them. `MenuBuilder::build()` runs the resolution inside `UrlParameters::forUser($user, …)`, so `{user}` refers to the user the menu is built for, not necessarily the authenticated one. Placeholders are resolved on every build and never cached. The published tree is cached; resolved items are not. A placeholder without a value makes the URL null, and the builder already drops links without a URL. On save, `ResolvableRoute` rejects unknown placeholders and checks the route with a sample value in place of each known placeholder, since the real value depends on the visitor. `{user.attribute}` refuses the model's `$hidden` attributes.

## Icons

The item form uses Icon Hub's `IconSelect`, which stores `provider:name` identifiers and validates them against Icon Hub's registry. `Support\Icons::safe()` is the single place that turns a stored icon into something Filament components accept. An Icon Hub identifier becomes the `Htmlable` from `IconHub::html()`; Filament wraps `Htmlable` icons in its own `fi-icon` span. A Blade Icons name is passed through after an existence check. Unknown icons in either format become `null`, because Filament throws on unknown icon names. `Icons::toHubId()` maps a Blade Icons name to the identifier of its set (through `BladeIconSetProvider::prefix()`), so the select can show values saved before the picker existed.

## Screen visibility

`Support\ScreenVisibility` is a readonly value object over `data.screens` (`from` and `until`, both `Enums\Breakpoint`). `ScreenVisibility::rules()` validates it in `MenuTreeGuard`, including rejecting ranges that never match, and `fromArray()` ignores unknown values when it reads. The classes go into `ResolvedMenuItem::wrapperAttributes()`, so every view that renders a wrapper (list, tree, columns and mega) applies them without its own code. Dropdown panel entries have no wrapper of their own, so `dropdown-item` wraps them in a `<div>` only when the wrapper has attributes. The media queries live in the `components` layer of `menu.css`, with doubled classes so they beat Filament's own `display` rules.

## Item form settings

`menu-builder.locales` and `menu-builder.item_form` (`slide_over`, `width`) configure the editor. The plugin methods of the same purpose override them, and the page reads them through `InteractsWithMenuPlugin::settings()`, which falls back to a plugin built from the config when the page runs outside a panel that registered the plugin.

## Page placement

Filament reads a page's cluster while the panel registers its pages, before any request. `MenuBuilderPlugin::register()` therefore hands the cluster to `MenuPlacements` and `ManageMenu` through `useCluster()`. It is kept in a static declared by `InteractsWithMenuPlugin`, not in `Page::$cluster`. That base property is shared by every page class in the panel, so assigning it would move all of them into the cluster. The resource placement uses Filament's `navigationParentItem` and group, resolved from the plugin at request time. Breadcrumbs of both pages go through `withParentBreadcrumbs()`, which prepends the resource's index page and then the cluster's breadcrumb.

## Label translations

Translations live in `data.label_translations` (locale => label), next to the other shared rendering keys, so they need no migration and they travel with drafts. `MenuTreeGuard` validates the locale keys and the string length. `MenuNode::translatedLabel()` looks up the exact locale, then its language. `MenuBuilder` prefers that over `MenuItemType::resolveLabel()`, which keeps the item type API unchanged. The locales offered in the form come from `MenuBuilderPlugin::locales()`: only the editor needs them, and the frontend reads whatever translations are stored.

## Authorization

`MenuAuthorizer` asks the policy registered for the menu item model for `viewAny`, `create`, `update`, `reorder`, `delete` and `publish`, with the placement key as the argument. Without a policy, access follows Filament's default: panel users may manage menus. Filament actions use `->authorize()`, so unauthorized actions are hidden and cannot be mounted or called. `moveItem()` checks `reorder` itself.

The pages optionally check Filament Shield page permissions before the policy. `InteractsWithMenuPlugin::getShieldPermission()` reads the page's key from `FilamentShield::getPages()` (so Shield's key builder and `pages.exclude` apply), and `canAccess()` requires `can(<permission>)` and at least one viewable placement. The check is on when the panel registers the `filament-shield` plugin, or when `MenuBuilderPlugin::shield()` forces it; Shield stays a suggested package, referenced only behind `class_exists()`.

## Decisions to review before 1.0

1. **Draft store = cache.** This is simple and fast, but a cache flush discards open drafts. The editor detects this and starts a fresh draft with a warning. A database store would make drafts durable.
2. **Optimistic concurrency.** Saving over a newer version is refused. Merging concurrent edits is not attempted.
3. **Type data is JSON.** It is portable and needs no migrations for custom types, but you cannot query links by URL or route in SQL. Menus are small and cached, so that trade-off seems right.
4. **Model-backed items store only `record_id`.** The model class comes from the item type, not a morph column. Renaming a type key therefore orphans its items (they are hidden, not deleted).
5. **Routes are validated when saving.** A route removed later makes future saves of that placement fail until the item is fixed. The frontend just hides it.
6. **No keyboard reordering yet.** Drag & drop is pointer-based. Move up/down or indent/outdent actions would improve accessibility.
7. **Unknown types and visibility rules are hidden on the frontend and rejected when saving.** This fails closed, but removing a type from code hides its items silently.
8. **Rendering options share the `data` JSON with type data.** This avoids a migration, but custom item types must not use the reserved keys. A dedicated `options` column would separate them at the cost of a schema change.
9. **Arbitrary attributes are a trusted-administrator feature.** Names and values cannot break the HTML, but `x-on:*` and `wire:*` attributes do run frontend behavior. That is intended, and it relies on menu editing being restricted to trusted users (see the policy).
10. **Frontend assets are inline.** This needs no build step or publishing and works outside Filament. A strict CSP needs the Vite nonce, or the assets switched off in favor of the host's own bundle.
