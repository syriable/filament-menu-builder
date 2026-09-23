<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;

/**
 * Creates raw rows without tree validation. Intended for tests only; use
 * the package actions to build menus in application code and seeders.
 *
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'placement' => 'header',
            'type' => 'link',
            'label' => $this->faker->words(2, true),
            'data' => ['link_type' => 'url', 'url' => '/'.$this->faker->slug(2)],
            'visibility' => 'everyone',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
