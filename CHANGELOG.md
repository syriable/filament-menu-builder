# Changelog

All notable changes to `filament-menu-builder` will be documented in this file.

## Unreleased

- Arbitrary HTML attributes per menu item (key/value editor, stored in `data.attributes`, validated names, escaped values, boolean attribute handling, item or wrapper target).
- "Render as" link, button or heading. Buttons need no URL.
- Badge positions `start`, `end` and `top`, using logical (RTL-aware) positioning.
- Blade components: `menu`, `items`, `item` and `badge`, plus `header`, `footer` and `sidebar` wrappers. Nested dropdowns are detected from children, flip when there is not enough viewport space, collapse into an accordion on small screens and work in RTL and LTR.
- The tree editor can now move a child out of its parent: drop into the indentation gutter, or on the new root drop zone.
- `ResolvedMenuItem` gains `renderAs`, `attributes`, `attributeTarget` and `badgePosition` (additive, with backward-compatible defaults).

- Initial release: dynamic placements, extensible and model-backed item types, drag & drop tree editor with server-side drafts, transactional publishing, centralized tree validation, cached frontend builder and policy-based authorization.
