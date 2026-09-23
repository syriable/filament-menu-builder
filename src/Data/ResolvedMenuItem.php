<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\View\ComponentAttributeBag;
use JsonSerializable;
use Syriable\Filament\Plugins\MenuBuilder\Enums\AttributeTarget;
use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes;

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
     * @param  array<string, string|true>  $attributes  Custom HTML attributes, validated and normalized.
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
        public RenderAs $renderAs = RenderAs::Link,
        public array $attributes = [],
        public AttributeTarget $attributeTarget = AttributeTarget::Item,
        public BadgePosition $badgePosition = BadgePosition::End,
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    public function isLink(): bool
    {
        return $this->renderAs === RenderAs::Link && $this->url !== null;
    }

    public function isButton(): bool
    {
        return $this->renderAs === RenderAs::Button;
    }

    public function isHeading(): bool
    {
        return ! $this->isLink() && ! $this->isButton();
    }

    /**
     * Items with children are rendered as dropdowns (or nested lists).
     */
    public function isDropdown(): bool
    {
        return $this->hasChildren();
    }

    /**
     * A button setting stored by the button item type (size, outlined, icon_position).
     */
    public function buttonOption(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function hasBadge(): bool
    {
        return $this->badge !== null && $this->badge !== '';
    }

    /**
     * Escaped attributes for the item's own element (<a>, <button>, heading).
     */
    public function itemAttributes(): ComponentAttributeBag
    {
        return HtmlAttributes::bag($this->attributeTarget === AttributeTarget::Item ? $this->attributes : []);
    }

    /**
     * Escaped attributes for the item's wrapper (<li>).
     */
    public function wrapperAttributes(): ComponentAttributeBag
    {
        return HtmlAttributes::bag($this->attributeTarget === AttributeTarget::Wrapper ? $this->attributes : []);
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
            'render_as' => $this->renderAs->value,
            'attributes' => $this->attributes,
            'attribute_target' => $this->attributeTarget->value,
            'badge_position' => $this->badgePosition->value,
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
