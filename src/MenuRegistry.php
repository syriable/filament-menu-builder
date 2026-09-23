<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Illuminate\Contracts\Auth\Authenticatable;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\UnknownItemType;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\UnknownPlacement;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\HeadingType;
use Syriable\Filament\Plugins\MenuBuilder\ItemTypes\LinkType;

/**
 * Runtime registry of placements, item types and visibility rules.
 *
 * Registered as a singleton; the host application adds to it from a
 * service provider (usually through the Menu facade).
 */
class MenuRegistry
{
    /** @var array<string, MenuPlacement> */
    protected array $placements = [];

    /** @var array<string, MenuItemType> */
    protected array $itemTypes = [];

    /** @var array<string, MenuVisibility> */
    protected array $visibilities = [];

    /**
     * @param  array<string, array<string, mixed>>  $placements  Placements declared in the config file.
     */
    public function __construct(array $placements = [])
    {
        $this->registerItemType(HeadingType::make());
        $this->registerItemType(LinkType::make());

        $this->registerVisibility(MenuVisibility::make(
            MenuVisibility::EVERYONE,
            static fn (): string => self::translate('visibility.everyone'),
            static fn (): bool => true,
        ));
        $this->registerVisibility(MenuVisibility::make(
            MenuVisibility::GUESTS,
            static fn (): string => self::translate('visibility.guests'),
            static fn (?Authenticatable $user): bool => $user === null,
        ));
        $this->registerVisibility(MenuVisibility::make(
            MenuVisibility::AUTHENTICATED,
            static fn (): string => self::translate('visibility.authenticated'),
            static fn (?Authenticatable $user): bool => $user !== null,
        ));

        foreach ($placements as $key => $config) {
            $this->registerPlacement(MenuPlacement::fromArray((string) $key, $config));
        }
    }

    public static function translate(string $key): string
    {
        $translation = __("menu-builder::menu-builder.{$key}");

        return is_string($translation) ? $translation : $key;
    }

    public function registerPlacement(MenuPlacement ...$placements): static
    {
        foreach ($placements as $placement) {
            $this->placements[$placement->getKey()] = $placement;
        }

        return $this;
    }

    public function registerItemType(MenuItemType ...$types): static
    {
        foreach ($types as $type) {
            $this->itemTypes[$type->getKey()] = $type;
        }

        return $this;
    }

    public function registerVisibility(MenuVisibility ...$visibilities): static
    {
        foreach ($visibilities as $visibility) {
            $this->visibilities[$visibility->key] = $visibility;
        }

        return $this;
    }

    /**
     * @return array<string, MenuPlacement> Sorted by their sort order, then registration order.
     */
    public function placements(): array
    {
        $placements = $this->placements;

        uasort($placements, static fn (MenuPlacement $a, MenuPlacement $b): int => $a->getSort() <=> $b->getSort());

        return $placements;
    }

    public function hasPlacement(string $key): bool
    {
        return isset($this->placements[$key]);
    }

    public function placement(string $key): MenuPlacement
    {
        return $this->placements[$key] ?? throw UnknownPlacement::make($key);
    }

    /**
     * @return array<string, MenuItemType>
     */
    public function itemTypes(): array
    {
        return $this->itemTypes;
    }

    public function hasItemType(string $key): bool
    {
        return isset($this->itemTypes[$key]);
    }

    public function itemType(string $key): MenuItemType
    {
        return $this->itemTypes[$key] ?? throw UnknownItemType::make($key);
    }

    /**
     * @return array<string, MenuVisibility>
     */
    public function visibilities(): array
    {
        return $this->visibilities;
    }

    public function visibility(string $key): ?MenuVisibility
    {
        return $this->visibilities[$key] ?? null;
    }

    /**
     * Item types that may be used in a placement below a parent of the given
     * type, or at the root level when the parent type is null.
     *
     * @return array<string, MenuItemType>
     */
    public function allowedItemTypes(MenuPlacement $placement, ?string $parentType = null): array
    {
        $allowed = $placement->getItemTypes() ?? array_keys($this->itemTypes);

        $restriction = match (true) {
            $parentType === null => $placement->getRootItemTypes(),
            ! $this->hasItemType($parentType), ! $this->itemType($parentType)->canHaveChildItems() => [],
            default => $placement->getChildItemTypes($parentType),
        };

        if ($restriction !== null) {
            $allowed = array_intersect($allowed, $restriction);
        }

        $types = [];

        foreach ($allowed as $key) {
            if ($this->hasItemType($key)) {
                $types[$key] = $this->itemType($key);
            }
        }

        return $types;
    }
}
