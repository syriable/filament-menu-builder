<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Syriable\Filament\Plugins\MenuBuilder\Enums\AttributeTarget;
use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes;
use Syriable\Filament\Plugins\MenuBuilder\Support\TextStyle;

/**
 * Immutable attributes of a single menu item.
 *
 * The position of a node (its parent and sort order) is owned by the
 * MenuTree, so a node can be moved without being rebuilt.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class MenuNode implements Arrayable
{
    /**
     * Attributes that can be changed through forms and actions.
     */
    public const array EDITABLE_ATTRIBUTES = [
        'type', 'label', 'data', 'icon', 'color', 'badge', 'badge_color', 'visibility', 'is_active',
    ];

    /**
     * Keys of `data` shared by every item type. They configure how an item is
     * rendered; everything else in `data` belongs to the item type.
     */
    public const string DATA_ATTRIBUTES = 'attributes';

    public const string DATA_RENDER_AS = 'render_as';

    public const string DATA_ATTRIBUTE_TARGET = 'attribute_target';

    public const string DATA_BADGE_POSITION = 'badge_position';

    public const string DATA_LABEL_TRANSLATIONS = 'label_translations';

    public const string DATA_TEXT_STYLE = 'text_style';

    /**
     * @param  array<string, mixed>  $data  Type specific data (url, route, record id, ...) and rendering options.
     */
    public function __construct(
        public string $key,
        public string $type,
        public ?int $id = null,
        public ?string $label = null,
        public array $data = [],
        public ?string $icon = null,
        public ?string $color = null,
        public ?string $badge = null,
        public ?string $badgeColor = null,
        public string $visibility = 'everyone',
        public bool $isActive = true,
    ) {}

    /**
     * Creates a node for an item that does not exist in the database yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function new(array $attributes): self
    {
        return self::fromAttributes('new-'.Str::lower((string) Str::ulid()), null, $attributes);
    }

    public static function fromModel(MenuItem $item): self
    {
        return new self(
            key: self::keyForId($item->id),
            type: $item->type,
            id: $item->id,
            label: $item->label,
            data: $item->data ?? [],
            icon: $item->icon,
            color: $item->color,
            badge: $item->badge,
            badgeColor: $item->badge_color,
            visibility: $item->visibility,
            isActive: $item->is_active,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes  Snake cased attributes, as used by forms and the database.
     */
    public static function fromAttributes(string $key, ?int $id, array $attributes): self
    {
        $data = $attributes['data'] ?? [];

        return new self(
            key: $key,
            type: (string) ($attributes['type'] ?? ''),
            id: $id,
            label: self::nullableString($attributes['label'] ?? null),
            data: is_array($data) ? $data : [],
            icon: self::nullableString($attributes['icon'] ?? null),
            color: self::nullableString($attributes['color'] ?? null),
            badge: self::nullableString($attributes['badge'] ?? null),
            badgeColor: self::nullableString($attributes['badge_color'] ?? null),
            visibility: self::nullableString($attributes['visibility'] ?? null) ?? 'everyone',
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    /**
     * Keys of persisted items are prefixed so they never become integer
     * array keys, which PHP does with numeric strings.
     */
    public static function keyForId(int $id): string
    {
        return 'item-'.$id;
    }

    /**
     * Returns a copy with the given snake cased attributes replaced. The key
     * and database id can never be changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function with(array $attributes): self
    {
        $attributes = Arr::only($attributes, self::EDITABLE_ATTRIBUTES);

        return self::fromAttributes($this->key, $this->id, [...$this->attributes(), ...$attributes]);
    }

    /**
     * The explicitly chosen root element, or null to derive it from the type.
     */
    public function renderAs(): ?RenderAs
    {
        $value = $this->data[self::DATA_RENDER_AS] ?? null;

        return is_string($value) ? RenderAs::tryFrom($value) : null;
    }

    /**
     * @return array<string, string|true>
     */
    public function htmlAttributes(): array
    {
        return HtmlAttributes::normalize($this->data[self::DATA_ATTRIBUTES] ?? []);
    }

    public function attributeTarget(): AttributeTarget
    {
        $value = $this->data[self::DATA_ATTRIBUTE_TARGET] ?? null;

        return (is_string($value) ? AttributeTarget::tryFrom($value) : null) ?? AttributeTarget::Item;
    }

    public function badgePosition(): BadgePosition
    {
        $value = $this->data[self::DATA_BADGE_POSITION] ?? null;

        return (is_string($value) ? BadgePosition::tryFrom($value) : null) ?? BadgePosition::End;
    }

    /**
     * The label translated into the given locale (`ar_SA` falls back to
     * `ar`), or null when there is no translation.
     */
    public function translatedLabel(string $locale): ?string
    {
        $translations = $this->data[self::DATA_LABEL_TRANSLATIONS] ?? null;

        if (! is_array($translations)) {
            return null;
        }

        foreach (array_unique([$locale, strtok($locale, '_-')]) as $candidate) {
            $label = $translations[$candidate] ?? null;

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return null;
    }

    public function textStyle(): TextStyle
    {
        return TextStyle::fromArray($this->data[self::DATA_TEXT_STYLE] ?? null);
    }

    public function isNew(): bool
    {
        return $this->id === null;
    }

    /**
     * Snake cased attributes matching the database columns.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'data' => $this->data,
            'icon' => $this->icon,
            'color' => $this->color,
            'badge' => $this->badge,
            'badge_color' => $this->badgeColor,
            'visibility' => $this->visibility,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'id' => $this->id, ...$this->attributes()];
    }

    /**
     * @param  array<string, mixed>  $array
     */
    public static function fromArray(array $array): self
    {
        $id = $array['id'] ?? null;

        return self::fromAttributes((string) $array['key'], is_numeric($id) ? (int) $id : null, $array);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
