# Changelog

All notable changes to `filament-menu-builder` will be documented in this file.

## Unreleased

- Added: `mega` frontend variant with `MegaMenu::register()`, the `mega-category` item type and `menu-builder.mega` config.
- Mega panels may extend past the menu and are kept inside the viewport instead, with a `--mb-mega-panel-viewport-gap` (16px) from its edges.
- The mega scroll arrows fade from gray-800 in dark mode, to match a gray-800 header.

- Fix: hover underlines (the default link underline and the "On hover" and "Always" text options) were never painted, because the label sits in an inline-flex box that the link's text decoration does not reach. Underlines are now drawn on the label.
- Dropdown chevrons animate with the open state: the first level turns up, nested levels nudge towards their panel. Honors `prefers-reduced-motion`.

- Configure the item form in `config/menu-builder.php`: `item_form.slide_over` and `item_form.width`. The plugin's `slideOver()` and new `modalWidth()` override them.
- Translation locales can be set in `config/menu-builder.locales`. `MenuBuilderPlugin::locales()` overrides them.
- Reorganized item form: essentials first, then collapsible Label translations, Appearance, Text and HTML attributes sections (collapsed when empty), with fields two per row.
- Text options per item (`data.text_style`): weight, size, italic, underline (always, on hover, never), letter case, cursor and hover color. They are rendered as `mb-*` classes and `--mb-item-hover-color`.
- Headings no longer underline on hover by default.

- Fix: the editor stylesheet is scoped to `.mb-editor`. Its `.mb-row` and `.mb-label` rules leaked onto frontend menus through `@filamentStyles`, causing a light hover background and near-black labels.
- Links, headings and dropdown entries now inherit the menu's text color and have no hover background. Items with an explicit color keep it.
- New `item-class`, `active-class` and `dropdown-class` props, `--mb-color`, `--mb-hover-color`, `--mb-active-color` and `--mb-dropdown-*` custom properties, and an `mb-active` class on active items.
- Translatable labels: `MenuBuilderPlugin::locales()` adds a label field per locale, stored in `data.label_translations`, and `Menu::build()` gains a `locale` argument (default: the app locale).
- The item form opens in a slide-over. Use `MenuBuilderPlugin::slideOver(false)` for a modal.

- The frontend renderer is built on Filament's Blade components: links and headings use `<x-filament::link>`, buttons `<x-filament::button>`, badges `<x-filament::badge>`, items with children `<x-filament::dropdown>` (nested at any depth, opening towards the inline end, with flip and shift), dropdown entries `dropdown.list.item` / `dropdown.header`, and accordion toggles `<x-filament::icon-button>`.
- New `direction` prop (`ltr`/`rtl`, defaulting to the locale's Filament direction) on `menu`, `header`, `footer` and `sidebar`.
- A link with children lists its own page as the first dropdown entry; unknown icons are skipped instead of throwing; custom item components also render dropdown entries.
- The package's own frontend assets shrink to layout CSS and an accordion script. Frontends now need Filament's styles and scripts (see "Filament on your frontend").

- **Button** is now a built-in item type (Heading, Link, Button), rendered with Filament's `<x-filament::button>`. Color, size, outline, icon and icon position are configurable, the URL is optional, and attributes are supported.
- The HTML attributes editor is its own section of the item form for every type. The per-item "Render as" select was removed from the form; the element now follows the item type. Existing `render_as` data keeps working.
- `MenuItemType::renderAs()` lets custom types render as buttons.
- The frontend menu CSS now lives in the `base` cascade layer, so Filament component styles and Tailwind utilities override it.

- Arbitrary HTML attributes per menu item (key/value editor, stored in `data.attributes`, validated names, escaped values, boolean attribute handling, item or wrapper target).
- "Render as" link, button or heading. Buttons need no URL.
- Badge positions `start`, `end` and `top`, using logical (RTL-aware) positioning.
- Blade components: `menu`, `items`, `item` and `badge`, plus `header`, `footer` and `sidebar` wrappers. Nested dropdowns are detected from children, flip when there is not enough viewport space, collapse into an accordion on small screens and work in RTL and LTR.
- The tree editor can now move a child out of its parent: drop into the indentation gutter, or on the new root drop zone.
- `ResolvedMenuItem` gains `renderAs`, `attributes`, `attributeTarget` and `badgePosition` (additive, with backward-compatible defaults).

- Initial release: dynamic placements, extensible and model-backed item types, drag & drop tree editor with server-side drafts, transactional publishing, centralized tree validation, cached frontend builder and policy-based authorization.
