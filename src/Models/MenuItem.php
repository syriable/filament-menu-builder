<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\Database\Factories\MenuItemFactory;

/**
 * A persisted (published) menu item.
 *
 * Structural changes must go through the package actions (or the Filament
 * editor) so the tree rules of the placement are enforced; writing to this
 * model directly bypasses validation.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $placement
 * @property string $type
 * @property string|null $label
 * @property array<string, mixed>|null $data
 * @property string|null $icon
 * @property string|null $color
 * @property string|null $badge
 * @property string|null $badge_color
 * @property string $visibility
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'visibility' => 'everyone',
        'is_active' => true,
        'sort_order' => 0,
    ];

    #[Override]
    public function getTable(): string
    {
        return config()->string('menu-builder.table_name', 'menu_items');
    }

    /**
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * @return HasMany<static, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeInPlacement(Builder $query, string $placement): void
    {
        $query->where('placement', $placement);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'data' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }
}
