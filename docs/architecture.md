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
resources/dist/                      Alpine component (drag & drop) + stylesheet
resources/views/                     Filament pages and the recursive tree partial
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
├── MenuBuilder.php                  published tree → ResolvedMenuItem[]
├── ItemTypes/                       built-in heading and link types
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
├── Support/                         MenuRepository (+ cache), UrlResolver, VisibilityResolver, MenuAuthorizer
├── Rules/ResolvableRoute.php
├── Data/ResolvedMenuItem.php        frontend DTO
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
```

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
- Only a completed drop reaches the server, as one semantic call: `moveItem(key, targetKey, 'before'|'after'|'inside')`. The drop event recomputes the position itself, so it never relies on a highlight left over from an earlier event. The server validates the call and re-renders the tree. There is no per-mousemove traffic, and no tree state is kept in JavaScript.
- **Tree rows carry no Alpine directives.** Livewire's morph can move rows after a reorder and tear down the Alpine bindings of a moved element, so the drag and click listeners live on the root element and find their row through `closest('.mb-row')` and `data-key`. Collapsed items are hidden by a generated `<style wire:ignore>` block of `[data-key]` selectors. Livewire's own `wire:click` on the row buttons is not affected.
- Expand and collapse state is saved in `localStorage` under a key per placement (`menu-builder.{placement}.collapsed`).
- While the draft is dirty, `beforeunload` and `livewire:navigate` ask for confirmation.

Alpine names on the root are specific (`toggleItem`, `expandAllItems`, and so on) because Filament's section component defines `isCollapsed` and similar properties in enclosing Alpine scopes.

## Frontend builder

`MenuBuilder::build()`:

1. Loads the published tree: one query, or none when cached.
2. Walks it once to collect active and visible keys, pruning invisible subtrees.
3. Loads the linked records with one `whereIn` query per model-backed type.
4. Builds `ResolvedMenuItem`s recursively. Labels and URLs are resolved by the item type, link items go through `UrlResolver`, and items whose URL or record cannot be resolved are dropped. Items are marked `isCurrent` or `isActiveTrail`.

## Authorization

`MenuAuthorizer` asks the policy registered for the menu item model for `viewAny`, `create`, `update`, `reorder`, `delete` and `publish`, with the placement key as the argument. Without a policy, access follows Filament's default: panel users may manage menus. Filament actions use `->authorize()`, so unauthorized actions are hidden and cannot be mounted or called. `moveItem()` checks `reorder` itself.

## Decisions to review before 1.0

1. **Draft store = cache.** This is simple and fast, but a cache flush discards open drafts. The editor detects this and starts a fresh draft with a warning. A database store would make drafts durable.
2. **Optimistic concurrency.** Saving over a newer version is refused. Merging concurrent edits is not attempted.
3. **Type data is JSON.** It is portable and needs no migrations for custom types, but you cannot query links by URL or route in SQL. Menus are small and cached, so that trade-off seems right.
4. **Model-backed items store only `record_id`.** The model class comes from the item type, not a morph column. Renaming a type key therefore orphans its items (they are hidden, not deleted).
5. **Routes are validated when saving.** A route removed later makes future saves of that placement fail until the item is fixed. The frontend just hides it.
6. **No keyboard reordering yet.** Drag & drop is pointer-based. Move up/down or indent/outdent actions would improve accessibility.
7. **Unknown types and visibility rules are hidden on the frontend and rejected when saving.** This fails closed, but removing a type from code hides its items silently.
