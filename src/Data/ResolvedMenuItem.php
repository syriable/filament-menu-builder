<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A frontend-ready menu item: visible, with its label and URL resolved.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ResolvedMenuItem implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<ResolvedMenuItem>  $children
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $label,
        public ?string $url,
        public int $depth,
        public ?string $icon = null,
        public ?string $color = null,
        public ?string $badge = null,
        public ?string $badgeColor = null,
        public bool $openInNewTab = false,
        public bool $isCurrent = false,
        public bool $isActiveTrail = false,
        public array $data = [],
        public array $children = [],
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * True when this item or one of its descendants is the current page.
     */
    public function isActive(): bool
    {
        return $this->isCurrent || $this->isActiveTrail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'url' => $this->url,
            'depth' => $this->depth,
            'icon' => $this->icon,
            'color' => $this->color,
            'badge' => $this->badge,
            'badge_color' => $this->badgeColor,
            'open_in_new_tab' => $this->openInNewTab,
            'is_current' => $this->isCurrent,
            'is_active_trail' => $this->isActiveTrail,
            'data' => $this->data,
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
