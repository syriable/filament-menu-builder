<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;

return [

    /*
    |--------------------------------------------------------------------------
    | Menu item model & table
    |--------------------------------------------------------------------------
    |
    | You may extend the default model to add relationships or scopes. The
    | replacement must extend the package model.
    |
    */

    'model' => MenuItem::class,

    'table_name' => 'menu_items',

    /*
    |--------------------------------------------------------------------------
    | Placements
    |--------------------------------------------------------------------------
    |
    | Placements are the named locations your application renders menus in
    | (header, footer, sidebar, ...). Simple placements can be declared here;
    | anything more advanced can be registered from a service provider with
    | Menu::registerPlacement(MenuPlacement::make('header')->...).
    |
    | Supported keys: label, description, icon, sort, max_depth, item_types,
    | root_item_types and child_item_types (parent type => allowed types).
    |
    */

    'placements' => [
        // 'header' => [
        //     'label' => 'Header',
        //     'icon' => 'heroicon-o-bars-3',
        // ],
        // 'footer' => [
        //     'label' => 'Footer',
        //     'max_depth' => 2,
        //     'root_item_types' => ['heading'],
        //     'child_item_types' => ['heading' => ['link'], 'link' => []],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Label translations
    |--------------------------------------------------------------------------
    |
    | The locales item labels can be translated into. The item form shows one
    | label field per locale; the frontend uses the label of the current
    | locale and falls back to the default label. Use a list (['en', 'ar'])
    | or locale => name pairs. MenuBuilderPlugin::locales() overrides this.
    |
    */

    'locales' => [
        // 'en' => 'English',
        // 'ar' => 'العربية',
    ],

    /*
    |--------------------------------------------------------------------------
    | Item form
    |--------------------------------------------------------------------------
    |
    | How the form to create and edit items opens: as a slide-over or as a
    | centered modal, and how wide it is. Widths: xs, sm, md, lg, xl, 2xl,
    | 3xl, 4xl, 5xl, 6xl, 7xl, full or screen. The plugin methods
    | slideOver() and modalWidth() override these values per panel.
    |
    */

    'item_form' => [
        'slide_over' => true,
        'width' => '2xl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The *published* tree of every placement is cached until it is saved
    | again. Drafts are never cached as published data. A `null` ttl caches
    | the tree until the next publish.
    |
    */

    'cache' => [
        'enabled' => env('MENU_BUILDER_CACHE', true),
        'store' => null,
        'prefix' => 'menu-builder',
        'ttl' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Drafts
    |--------------------------------------------------------------------------
    |
    | While an administrator edits a menu, the working copy lives in a
    | server-side draft store instead of the database. The default store
    | uses Laravel's cache, which works on shared hosting. The ttl (seconds)
    | bounds how long an abandoned draft is kept.
    |
    */

    'drafts' => [
        'store' => null,
        'ttl' => 60 * 60 * 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend components
    |--------------------------------------------------------------------------
    |
    | <x-menu-builder::menu> prints a small structural stylesheet and dropdown
    | script once per page. Disable this to provide your own.
    |
    */

    'frontend' => [
        'assets' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route picker
    |--------------------------------------------------------------------------
    |
    | Named GET routes are offered in the "route" dropdown of link items.
    | Routes matching one of these patterns are hidden from that list.
    |
    */

    'routes' => [
        'exclude' => [
            'filament.*',
            'livewire.*',
            'ignition.*',
            'debugbar.*',
            'sanctum.*',
            'storage.*',
            'horizon.*',
            'telescope.*',
            'pulse.*',
            'boost.*',
        ],
    ],

];
