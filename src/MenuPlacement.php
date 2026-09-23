<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Illuminate\Support\Str;

/**
 * A named location in which the host application renders a menu, together
 * with the structural rules that apply to it.
 *
 * Rules are optional; a placement without rules accepts every registered
 * item type at any depth.
 */
class MenuPlacement
{
    protected ?string $label = null;

    protected ?string $description = null;

    protected ?string $icon = null;

    protected int $sort = 0;

    protected ?int $maxDepth = null;

    /** @var list<string>|null */
    protected ?array $itemTypes = null;

    /** @var list<string>|null */
    protected ?array $rootItemTypes = null;

    /** @var array<string, list<string>> */
    protected array $childItemTypes = [];

    final public function __construct(protected string $key) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $key, array $config): static
    {
        $placement = static::make($key)
            ->label(self::stringOrNull($config['label'] ?? null))
            ->description(self::stringOrNull($config['description'] ?? null))
            ->icon(self::stringOrNull($config['icon'] ?? null))
            ->sort(is_numeric($config['sort'] ?? null) ? (int) $config['sort'] : 0)
            ->maxDepth(is_numeric($config['max_depth'] ?? null) ? (int) $config['max_depth'] : null);

        if (is_array($config['item_types'] ?? null)) {
            $placement->itemTypes(self::stringList($config['item_types']));
        }

        if (is_array($config['root_item_types'] ?? null)) {
            $placement->rootItemTypes(self::stringList($config['root_item_types']));
        }

        foreach ((array) ($config['child_item_types'] ?? []) as $parentType => $types) {
            $placement->childItemTypes((string) $parentType, self::stringList((array) $types));
        }

        return $placement;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Maximum number of levels, 1 meaning root items only. Null is unlimited.
     */
    public function maxDepth(?int $depth): static
    {
        $this->maxDepth = $depth === null ? null : max(1, $depth);

        return $this;
    }

    /**
     * Restricts the item types that may be used anywhere in this placement.
     *
     * @param  list<string>|null  $types
     */
    public function itemTypes(?array $types): static
    {
        $this->itemTypes = $types;

        return $this;
    }

    /**
     * Restricts the item types that may be placed at the root level.
     *
     * @param  list<string>|null  $types
     */
    public function rootItemTypes(?array $types): static
    {
        $this->rootItemTypes = $types;

        return $this;
    }

    /**
     * Restricts the item types that may be placed below an item of the
     * given type. An empty list means that type cannot have children.
     *
     * @param  list<string>  $types
     */
    public function childItemTypes(string $parentType, array $types): static
    {
        $this->childItemTypes[$parentType] = $types;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->key);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    /**
     * @return list<string>|null
     */
    public function getItemTypes(): ?array
    {
        return $this->itemTypes;
    }

    /**
     * @return list<string>|null
     */
    public function getRootItemTypes(): ?array
    {
        return $this->rootItemTypes;
    }

    /**
     * @return list<string>|null Null when the placement has no rule for that parent type.
     */
    public function getChildItemTypes(string $parentType): ?array
    {
        return $this->childItemTypes[$parentType] ?? null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private static function stringList(array $values): array
    {
        return array_values(array_map(strval(...), array_filter($values, is_scalar(...))));
    }
}
