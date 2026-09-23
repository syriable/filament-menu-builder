<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tree;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Syriable\Filament\Plugins\MenuBuilder\Enums\AttributeTarget;
use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\MenuRegistry;
use Syriable\Filament\Plugins\MenuBuilder\Support\HtmlAttributes;

/**
 * The single source of truth for menu tree rules.
 *
 * Every write path (Filament editor, drag & drop, programmatic actions,
 * seeders, importers) validates through this class, so a rule only has to
 * be defined once, in the placement or item type configuration.
 *
 * Structural rules are always checked for the whole tree in O(n). Item data
 * (labels, URLs, custom type data) is checked for the given keys, or for
 * every item when no keys are given.
 */
final readonly class MenuTreeGuard
{
    public function __construct(
        private MenuRegistry $registry,
        private ValidationFactory $validator,
    ) {}

    /**
     * @param  list<string>|null  $keys  Items whose data is validated; null validates all items.
     * @return list<TreeViolation>
     */
    public function validate(MenuTree $tree, ?array $keys = null): array
    {
        if (! $this->registry->hasPlacement($tree->placement)) {
            return [new TreeViolation(__('menu-builder::menu-builder.validation.unknown_placement', ['placement' => $tree->placement]))];
        }

        $placement = $this->registry->placement($tree->placement);
        $violations = $this->validateStructure($tree, $placement);

        foreach ($keys ?? array_keys($tree->nodes()) as $key) {
            if ($tree->has($key)) {
                array_push($violations, ...$this->validateItem($tree->get($key)));
            }
        }

        return $violations;
    }

    /**
     * @param  list<string>|null  $keys
     *
     * @throws InvalidMenuTree
     */
    public function assertValid(MenuTree $tree, ?array $keys = null): void
    {
        $violations = $this->validate($tree, $keys);

        if ($violations !== []) {
            throw new InvalidMenuTree($violations);
        }
    }

    /**
     * @return list<TreeViolation>
     */
    private function validateStructure(MenuTree $tree, MenuPlacement $placement): array
    {
        $violations = [];
        $visited = 0;
        $maxDepth = $placement->getMaxDepth();

        /** @var array<string, array<string, MenuItemType>> $allowedByParentType */
        $allowedByParentType = [];

        foreach ($tree->walk() as $key => $depth) {
            $visited++;
            $node = $tree->get($key);
            $label = $this->describe($node);

            if (! $this->registry->hasItemType($node->type)) {
                $violations[] = new TreeViolation(
                    __('menu-builder::menu-builder.validation.unknown_type', ['item' => $label, 'type' => $node->type]),
                    $key,
                    'type',
                );

                continue;
            }

            $parentKey = $tree->parentOf($key);
            $parentType = $parentKey === null ? null : $tree->get($parentKey)->type;
            $allowed = $allowedByParentType[$parentType ?? ''] ??= $this->registry->allowedItemTypes($placement, $parentType);

            if (! isset($allowed[$node->type])) {
                $violations[] = new TreeViolation($this->placementViolation($placement, $node, $parentType), $key, 'type');
            }

            if ($maxDepth !== null && $depth > $maxDepth) {
                $violations[] = new TreeViolation(
                    trans_choice('menu-builder::menu-builder.validation.max_depth', $maxDepth, [
                        'item' => $label,
                        'placement' => $placement->getLabel(),
                        'depth' => $maxDepth,
                    ]),
                    $key,
                );
            }
        }

        if ($visited !== $tree->count()) {
            $violations[] = new TreeViolation(__('menu-builder::menu-builder.validation.detached'));
        }

        return $violations;
    }

    private function placementViolation(MenuPlacement $placement, MenuNode $node, ?string $parentType): string
    {
        $replace = [
            'item' => $this->describe($node),
            'type' => $this->typeLabel($node->type),
            'parent' => $parentType === null ? '' : $this->typeLabel($parentType),
            'placement' => $placement->getLabel(),
        ];

        return match (true) {
            ! in_array($node->type, $placement->getItemTypes() ?? [$node->type], true) => __('menu-builder::menu-builder.validation.type_not_allowed', $replace),
            $parentType === null => __('menu-builder::menu-builder.validation.root_not_allowed', $replace),
            ! $this->registry->hasItemType($parentType) || ! $this->registry->itemType($parentType)->canHaveChildItems(),
            $placement->getChildItemTypes($parentType) === [] => __('menu-builder::menu-builder.validation.no_children', $replace),
            default => __('menu-builder::menu-builder.validation.child_not_allowed', $replace),
        };
    }

    /**
     * @return list<TreeViolation>
     */
    private function validateItem(MenuNode $node): array
    {
        if (! $this->registry->hasItemType($node->type)) {
            return [];
        }

        $type = $this->registry->itemType($node->type);
        $violations = [];

        $attributes = $this->validator->make($node->attributes(), [
            'label' => [$type->resolvesLabel() ? 'nullable' : 'required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'badge' => ['nullable', 'string', 'max:50'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'visibility' => ['required', Rule::in(array_keys($this->registry->visibilities()))],
        ]);

        foreach ($attributes->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $violations[] = new TreeViolation($message, $node->key, $field);
            }
        }

        $rendering = $this->validator->make($node->data, [
            MenuNode::DATA_RENDER_AS => ['nullable', Rule::enum(RenderAs::class)->only(
                $type->hasUrl() ? RenderAs::cases() : [RenderAs::Button, RenderAs::Heading],
            )],
            MenuNode::DATA_ATTRIBUTE_TARGET => ['nullable', Rule::enum(AttributeTarget::class)],
            MenuNode::DATA_BADGE_POSITION => ['nullable', Rule::enum(BadgePosition::class)],
            MenuNode::DATA_ATTRIBUTES => ['nullable', 'array', $this->htmlAttributesRule()],
            MenuNode::DATA_LABEL_TRANSLATIONS => ['nullable', 'array', $this->localeKeysRule()],
            MenuNode::DATA_LABEL_TRANSLATIONS.'.*' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($rendering->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $violations[] = new TreeViolation($message, $node->key, 'data.'.$field);
            }
        }

        $rules = $type->getRules();

        if ($rules !== []) {
            $data = $this->validator->make($node->data, $rules);

            foreach ($data->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $violations[] = new TreeViolation($message, $node->key, 'data.'.$field);
                }
            }
        }

        return $violations;
    }

    /**
     * Attribute names must not be able to break out of the attribute syntax;
     * values must be plain scalars.
     */
    private function htmlAttributesRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $name => $attributeValue) {
                if (! HtmlAttributes::isValidName(is_string($name) ? trim($name) : $name)) {
                    $fail(__('menu-builder::menu-builder.validation.attribute_name', ['name' => (string) $name]));

                    continue;
                }

                if (! (is_scalar($attributeValue) || $attributeValue === null) || mb_strlen((string) $attributeValue) > 2000) {
                    $fail(__('menu-builder::menu-builder.validation.attribute_value', ['name' => (string) $name]));
                }
            }
        };
    }

    private function localeKeysRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            foreach (is_array($value) ? array_keys($value) : [] as $locale) {
                if (! is_string($locale) || preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
                    $fail(__('menu-builder::menu-builder.validation.locale', ['locale' => (string) $locale]));
                }
            }
        };
    }

    private function describe(MenuNode $node): string
    {
        return $node->label ?? $this->typeLabel($node->type);
    }

    private function typeLabel(string $type): string
    {
        return $this->registry->hasItemType($type) ? $this->registry->itemType($type)->getLabel() : $type;
    }
}
