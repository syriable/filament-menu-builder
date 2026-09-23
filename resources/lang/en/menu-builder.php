<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'Menus',
        'menu' => 'Menu',
    ],

    'placements' => [
        'empty' => 'No menu placements are registered yet. Register one with Menu::registerPlacement() or in config/menu-builder.php.',
        'items' => '{0} No items|{1} 1 item|[2,*] :count items',
        'manage' => 'Manage',
    ],

    'types' => [
        'heading' => 'Heading',
        'link' => 'Link',
    ],

    'link_types' => [
        'url' => 'URL',
        'route' => 'Route',
    ],

    'visibility' => [
        'everyone' => 'Everyone',
        'guests' => 'Guests only',
        'authenticated' => 'Signed-in users only',
    ],

    'colors' => [
        'primary' => 'Primary',
        'gray' => 'Gray',
        'info' => 'Info',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
    ],

    'fields' => [
        'type' => 'Type',
        'label' => 'Label',
        'label_optional' => 'Leave empty to use the title of the linked record.',
        'link_type' => 'Link to',
        'url' => 'URL',
        'route' => 'Route',
        'route_parameters' => 'Route parameters',
        'parameter' => 'Parameter',
        'value' => 'Value',
        'new_tab' => 'Open in a new tab',
        'appearance' => 'Appearance',
        'icon' => 'Icon',
        'color' => 'Color',
        'badge' => 'Badge',
        'badge_color' => 'Badge color',
        'visibility' => 'Visible to',
        'is_active' => 'Active',
    ],

    'actions' => [
        'create' => 'Add item',
        'create_child' => 'Add child item',
        'create_heading' => 'Add menu item',
        'create_child_heading' => 'Add item below ":parent"',
        'add' => 'Add',
        'apply' => 'Apply',
        'edit' => 'Edit',
        'edit_heading' => 'Edit ":item"',
        'delete' => 'Delete',
        'delete_heading' => 'Delete ":item"?',
        'delete_description' => 'The item is removed from the draft. The published menu changes when you save.',
        'delete_with_children' => '{1} This item contains 1 child item. Deleting it will also delete its descendants.|[2,*] This item contains :count child items. Deleting it will also delete its descendants.',
        'save' => 'Save changes',
        'discard' => 'Discard changes',
        'discard_description' => 'All unsaved changes are lost and the published menu is loaded again.',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
    ],

    'status' => [
        'saved' => 'Saved',
        'unsaved' => 'Unsaved changes',
        'leave_warning' => 'You have unsaved menu changes. Leave anyway?',
    ],

    'tree' => [
        'empty' => 'This menu has no items yet.',
        'drag' => 'Drag to move',
        'toggle' => 'Expand or collapse',
        'new' => 'New',
        'inactive' => 'Inactive',
    ],

    'notifications' => [
        'saved' => 'Menu saved',
        'discarded' => 'Changes discarded',
        'invalid' => 'The menu cannot be changed like that',
        'unauthorized' => 'You are not allowed to do this.',
        'stale_title' => 'The menu was changed by someone else',
        'stale_body' => 'Somebody saved this menu after you started editing it. Discard your changes to load the latest version.',
        'draft_expired' => 'Your draft expired, so the published menu was loaded again.',
    ],

    'validation' => [
        'unknown_placement' => 'The menu placement ":placement" is not registered.',
        'unknown_type' => '":item" has the unknown item type ":type".',
        'type_not_allowed' => '":type" items cannot be used in the :placement menu.',
        'root_not_allowed' => '":type" items cannot be placed at the top level of the :placement menu.',
        'child_not_allowed' => '":type" items cannot be placed below ":parent" items.',
        'no_children' => '":parent" items cannot contain other items.',
        'max_depth' => '{1} The :placement menu cannot have nested items.|[2,*] The :placement menu allows at most :depth levels.',
        'detached' => 'The menu contains items that are not connected to the tree.',
        'cycle' => 'An item cannot be moved below itself or one of its descendants.',
        'missing_item' => 'The menu item ":item" no longer exists.',
        'missing_parent' => 'The parent item does not exist in this menu.',
        'route_missing' => 'The route ":route" does not exist.',
        'route_parameters' => 'The route ":route" needs a value for each of its required parameters.',
    ],

];
