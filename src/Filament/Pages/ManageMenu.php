<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Override;
use Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraft;
use Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraftManager;
use Syriable\Filament\Plugins\MenuBuilder\Enums\AttributeTarget;
use Syriable\Filament\Plugins\MenuBuilder\Enums\BadgePosition;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\DraftNotFound;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\StaleMenu;
use Syriable\Filament\Plugins\MenuBuilder\Filament\Pages\Concerns\InteractsWithMenuPlugin;
use Syriable\Filament\Plugins\MenuBuilder\MenuItemType;
use Syriable\Filament\Plugins\MenuBuilder\MenuPlacement;
use Syriable\Filament\Plugins\MenuBuilder\MenuVisibility;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuAuthorizer;
use Syriable\Filament\Plugins\MenuBuilder\Tree\DropPosition;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;
use ValueError;

/**
 * Tree editor of one placement.
 *
 * Every change is applied to a server-side draft (see MenuDraftManager);
 * nothing reaches the published menu until "Save changes" publishes the
 * complete, validated draft in one transaction. The browser only holds the
 * draft id, so Livewire payloads stay small even for large menus.
 */
class ManageMenu extends Page
{
    use InteractsWithMenuPlugin;

    protected static ?string $slug = 'menus/{placement}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'menu-builder::filament.pages.manage-menu';

    #[Locked]
    public string $placement = '';

    #[Locked]
    public string $draftId = '';

    protected ?MenuDraft $cachedDraft = null;

    public function mount(string $placement): void
    {
        abort_unless(static::registry()->hasPlacement($placement), 404);
        abort_unless($this->can(MenuAuthorizer::VIEW_ANY, $placement), 403);

        $this->placement = $placement;
        $this->draftId = $this->drafts()->start($placement)->id;
    }

    #[Override]
    public static function canAccess(): bool
    {
        return static::canViewAnyPlacement();
    }

    /**
     * Tools such as Filament Shield read the title of pages that were never
     * mounted, so the title must not require a placement.
     */
    #[Override]
    public function getTitle(): string|Htmlable
    {
        return $this->findPlacement()?->getLabel() ?? $this->translate('navigation.menu');
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return $this->findPlacement()?->getDescription();
    }

    /**
     * @return array<string>
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        return [
            MenuPlacements::getUrl() => $this->translate('navigation.label'),
            $this->getTitle() instanceof Htmlable ? $this->getTitle()->toHtml() : $this->getTitle(),
        ];
    }

    public function findPlacement(): ?MenuPlacement
    {
        return static::registry()->hasPlacement($this->placement)
            ? static::registry()->placement($this->placement)
            : null;
    }

    public function getPlacement(): MenuPlacement
    {
        return static::registry()->placement($this->placement);
    }

    public function isDirty(): bool
    {
        return $this->draft()->isDirty();
    }

    /**
     * Called by the drag & drop script once an item was dropped.
     */
    public function moveItem(string $key, string $target, string $position): void
    {
        if (! $this->can(MenuAuthorizer::REORDER)) {
            $this->notifyUnauthorized();

            return;
        }

        try {
            $dropPosition = DropPosition::from($position);
        } catch (ValueError) {
            return;
        }

        $draft = $this->draft();

        try {
            $this->editor()->moveRelativeTo($draft->tree, $key, $target, $dropPosition);
        } catch (InvalidMenuTree $exception) {
            $this->notifyInvalid($exception);

            return;
        }

        $this->drafts()->save($draft);
    }

    public function createItemAction(): Action
    {
        return Action::make('createItem')
            ->label(__('menu-builder::menu-builder.actions.create'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(fn (array $arguments): string => filled($arguments['parent'] ?? null) && $this->draft()->tree->has((string) $arguments['parent'])
                ? __('menu-builder::menu-builder.actions.create_child_heading', ['parent' => $this->nodeLabel((string) $arguments['parent'])])
                : __('menu-builder::menu-builder.actions.create_heading'))
            ->modalSubmitActionLabel(__('menu-builder::menu-builder.actions.add'))
            ->authorize(fn (): bool => $this->can(MenuAuthorizer::CREATE))
            ->visible(fn (array $arguments): bool => $this->allowedTypes($this->parentKey($arguments)) !== [])
            ->fillForm(fn (array $arguments): array => [
                'type' => array_key_first($this->allowedTypes($this->parentKey($arguments))),
                'visibility' => 'everyone',
                'is_active' => true,
            ])
            ->schema(fn (array $arguments): array => $this->createFormSchema($this->parentKey($arguments)))
            ->action(function (array $data, array $arguments, Action $action): void {
                $parentKey = $this->parentKey($arguments);
                $allowed = $this->allowedTypes($parentKey);
                $type = count($allowed) === 1 ? (string) array_key_first($allowed) : (string) ($data['type'] ?? '');
                $draft = $this->draft();

                try {
                    $this->editor()->create($draft->tree, [...$data, 'type' => $type], $parentKey);
                } catch (InvalidMenuTree $exception) {
                    $this->reportFormViolations($exception, $action->getNestingIndex() ?? 0);
                }

                $this->drafts()->save($draft);
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label(__('menu-builder::menu-builder.actions.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $arguments): string => __('menu-builder::menu-builder.actions.edit_heading', [
                'item' => $this->nodeLabel((string) ($arguments['key'] ?? '')),
            ]))
            ->modalSubmitActionLabel(__('menu-builder::menu-builder.actions.apply'))
            ->authorize(fn (): bool => $this->can(MenuAuthorizer::UPDATE))
            ->fillForm(fn (array $arguments): array => $this->node($arguments)->attributes())
            ->schema(fn (array $arguments): array => $this->itemFormSchema($this->node($arguments)->type))
            ->action(function (array $data, array $arguments, Action $action): void {
                $draft = $this->draft();

                try {
                    $this->editor()->update($draft->tree, $this->node($arguments)->key, $data);
                } catch (InvalidMenuTree $exception) {
                    $this->reportFormViolations($exception, $action->getNestingIndex() ?? 0);
                }

                $this->drafts()->save($draft);
            });
    }

    public function deleteItemAction(): Action
    {
        return Action::make('deleteItem')
            ->label(__('menu-builder::menu-builder.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('menu-builder::menu-builder.actions.delete_heading', [
                'item' => $this->nodeLabel((string) ($arguments['key'] ?? '')),
            ]))
            ->modalDescription(function (array $arguments): string {
                $count = count($this->draft()->tree->descendantsOf($this->node($arguments)->key));

                return $count > 0
                    ? trans_choice('menu-builder::menu-builder.actions.delete_with_children', $count, ['count' => $count])
                    : __('menu-builder::menu-builder.actions.delete_description');
            })
            ->modalSubmitActionLabel(__('menu-builder::menu-builder.actions.delete'))
            ->authorize(fn (): bool => $this->can(MenuAuthorizer::DELETE))
            ->action(function (array $arguments): void {
                $draft = $this->draft();

                $this->editor()->delete($draft->tree, $this->node($arguments)->key);

                $this->drafts()->save($draft);
            });
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(__('menu-builder::menu-builder.actions.save'))
            ->icon(Heroicon::OutlinedCheck)
            ->keyBindings(['mod+s'])
            ->authorize(fn (): bool => $this->can(MenuAuthorizer::PUBLISH))
            ->disabled(fn (): bool => ! $this->isDirty())
            ->action(function (Action $action): void {
                try {
                    $this->drafts()->publish($this->draft());
                } catch (InvalidMenuTree $exception) {
                    $this->notifyInvalid($exception);

                    $action->halt();
                } catch (StaleMenu) {
                    Notification::make()
                        ->danger()
                        ->title(__('menu-builder::menu-builder.notifications.stale_title'))
                        ->body(__('menu-builder::menu-builder.notifications.stale_body'))
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('menu-builder::menu-builder.notifications.saved'))
                    ->send();
            });
    }

    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('menu-builder::menu-builder.actions.discard'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('menu-builder::menu-builder.actions.discard_description'))
            ->disabled(fn (): bool => ! $this->isDirty())
            ->action(function (): void {
                $this->drafts()->discard($this->draft());

                Notification::make()
                    ->success()
                    ->title(__('menu-builder::menu-builder.notifications.discarded'))
                    ->send();
            });
    }

    /**
     * Everything the tree view needs, computed once per render.
     *
     * @return array<string, mixed>
     */
    public function getTreeViewData(): array
    {
        $tree = $this->draft()->tree;
        $placement = $this->getPlacement();
        $registry = static::registry();

        $canHaveChildren = [];

        foreach (array_keys($registry->itemTypes()) as $type) {
            $canHaveChildren[$type] = $registry->allowedItemTypes($placement, $type) !== [];
        }

        return [
            'tree' => $tree,
            'labels' => $this->displayLabels($tree),
            'types' => $registry->itemTypes(),
            'visibilities' => $registry->visibilities(),
            'canHaveChildren' => $canHaveChildren,
            'maxDepth' => $placement->getMaxDepth(),
            'isDirty' => $this->isDirty(),
            'can' => [
                'create' => $this->can(MenuAuthorizer::CREATE),
                'update' => $this->can(MenuAuthorizer::UPDATE),
                'reorder' => $this->can(MenuAuthorizer::REORDER),
                'delete' => $this->can(MenuAuthorizer::DELETE),
            ],
        ];
    }

    /**
     * @return array<Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->createItemAction(),
            $this->discardAction(),
            $this->saveAction(),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function createFormSchema(?string $parentKey): array
    {
        $types = $this->allowedTypes($parentKey);
        $default = (string) array_key_first($types);

        return [
            ToggleButtons::make('type')
                ->label(__('menu-builder::menu-builder.fields.type'))
                ->options(array_map(static fn (MenuItemType $type): string => $type->getLabel(), $types))
                ->icons(array_filter(array_map(static fn (MenuItemType $type): ?string => $type->getIcon(), $types)))
                ->inline()
                ->required()
                ->in(array_keys($types))
                ->live()
                // The fields below depend on the type; hydrate their defaults when it changes.
                ->afterStateUpdated(static function (ToggleButtons $component): void {
                    $fields = $component->getContainer()->getComponent('item-fields');

                    if ($fields instanceof Component) {
                        $fields->getChildSchema()?->fill();
                    }
                })
                ->visible(count($types) > 1),
            Group::make(fn (Get $get): array => $this->itemFormSchema(
                isset($types[$type = (string) $get('type')]) ? $type : $default,
            ))->key('item-fields'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function itemFormSchema(string $type): array
    {
        $registry = static::registry();
        $definition = $registry->itemType($type);
        $colors = $this->colorOptions();

        return [
            TextInput::make('label')
                ->label(__('menu-builder::menu-builder.fields.label'))
                ->required(! $definition->resolvesLabel())
                ->helperText($definition->resolvesLabel() ? $this->translate('fields.label_optional') : null)
                ->maxLength(255),
            Group::make($definition->getFormSchema())
                ->statePath('data'),
            Section::make(__('menu-builder::menu-builder.fields.appearance'))
                ->schema([
                    TextInput::make('icon')
                        ->label(__('menu-builder::menu-builder.fields.icon'))
                        ->placeholder('heroicon-o-home')
                        ->maxLength(100),
                    Select::make('color')
                        ->label(__('menu-builder::menu-builder.fields.color'))
                        ->options($colors),
                    TextInput::make('badge')
                        ->label(__('menu-builder::menu-builder.fields.badge'))
                        ->maxLength(50),
                    Select::make('badge_color')
                        ->label(__('menu-builder::menu-builder.fields.badge_color'))
                        ->options($colors),
                    Group::make([
                        ToggleButtons::make(MenuNode::DATA_BADGE_POSITION)
                            ->label(__('menu-builder::menu-builder.fields.badge_position'))
                            ->options($this->enumOptions(BadgePosition::cases()))
                            ->default(BadgePosition::End->value)
                            ->inline()
                            ->grouped(),
                    ])->statePath('data'),
                ])
                ->columns(2)
                ->collapsible()
                ->compact(),
            Section::make(__('menu-builder::menu-builder.fields.rendering'))
                ->schema([
                    Select::make(MenuNode::DATA_RENDER_AS)
                        ->label(__('menu-builder::menu-builder.fields.render_as'))
                        ->placeholder(__('menu-builder::menu-builder.render_as.auto'))
                        ->options($this->enumOptions($definition->hasUrl() ? RenderAs::cases() : [RenderAs::Button, RenderAs::Heading]))
                        ->helperText(__('menu-builder::menu-builder.fields.render_as_help'))
                        ->live(),
                    Select::make(MenuNode::DATA_ATTRIBUTE_TARGET)
                        ->label(__('menu-builder::menu-builder.fields.attribute_target'))
                        ->options($this->enumOptions(AttributeTarget::cases()))
                        ->default(AttributeTarget::Item->value)
                        ->selectablePlaceholder(false),
                    KeyValue::make(MenuNode::DATA_ATTRIBUTES)
                        ->label(__('menu-builder::menu-builder.fields.attributes'))
                        ->keyLabel(__('menu-builder::menu-builder.fields.attribute'))
                        ->valueLabel(__('menu-builder::menu-builder.fields.value'))
                        ->helperText(__('menu-builder::menu-builder.fields.attributes_help'))
                        ->columnSpanFull(),
                ])
                ->statePath('data')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->compact(),
            Grid::make(2)->schema([
                Select::make('visibility')
                    ->label(__('menu-builder::menu-builder.fields.visibility'))
                    ->options(array_map(static fn (MenuVisibility $visibility): string => $visibility->getLabel(), $registry->visibilities()))
                    ->default('everyone')
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('menu-builder::menu-builder.fields.is_active'))
                    ->default(true)
                    ->inline(false),
            ]),
        ];
    }

    /**
     * @param  array<RenderAs|AttributeTarget|BadgePosition>  $cases
     * @return array<string, string>
     */
    protected function enumOptions(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /**
     * @return array<string, MenuItemType>
     */
    protected function allowedTypes(?string $parentKey): array
    {
        $tree = $this->draft()->tree;
        $parentType = $parentKey === null ? null : $tree->get($parentKey)->type;
        $maxDepth = $this->getPlacement()->getMaxDepth();

        if ($parentKey !== null && $maxDepth !== null && $tree->depthOf($parentKey) >= $maxDepth) {
            return [];
        }

        return static::registry()->allowedItemTypes($this->getPlacement(), $parentType);
    }

    /**
     * @return array<string, string>
     */
    protected function colorOptions(): array
    {
        return collect(['primary', 'gray', 'info', 'success', 'warning', 'danger'])
            ->mapWithKeys(static fn (string $color): array => [$color => __("menu-builder::menu-builder.colors.{$color}")])
            ->all();
    }

    /**
     * Labels for the editor. Items without a label of a model backed type
     * show the title of their record; records are loaded per type in one query.
     *
     * @return array<string, string>
     */
    protected function displayLabels(MenuTree $tree): array
    {
        $registry = static::registry();
        $recordKeys = [];

        foreach ($tree->nodes() as $node) {
            if ($node->label === null && $registry->hasItemType($node->type)) {
                $type = $registry->itemType($node->type);
                $recordKey = $type->getRecordKey($node);

                if ($type->isModelBacked() && $recordKey !== null) {
                    $recordKeys[$node->type][] = $recordKey;
                }
            }
        }

        /** @var array<string, array<int|string, Model>> $records */
        $records = [];

        foreach ($recordKeys as $type => $keys) {
            $records[$type] = $registry->itemType($type)->findRecords($keys);
        }

        $labels = [];

        foreach ($tree->nodes() as $key => $node) {
            if (! $registry->hasItemType($node->type)) {
                $labels[$key] = $node->label ?? $node->type;

                continue;
            }

            $type = $registry->itemType($node->type);
            $recordKey = $type->getRecordKey($node);

            $labels[$key] = $type->resolveLabel($node, $recordKey === null ? null : ($records[$node->type][$recordKey] ?? null));
        }

        return $labels;
    }

    protected function draft(): MenuDraft
    {
        if ($this->cachedDraft !== null) {
            return $this->cachedDraft;
        }

        try {
            $draft = $this->drafts()->find($this->draftId);
        } catch (DraftNotFound) {
            $draft = $this->drafts()->start($this->placement);
            $this->draftId = $draft->id;

            Notification::make()
                ->warning()
                ->title(__('menu-builder::menu-builder.notifications.draft_expired'))
                ->send();
        }

        abort_unless($draft->placement() === $this->placement, 403);

        return $this->cachedDraft = $draft;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function node(array $arguments): MenuNode
    {
        $key = $arguments['key'] ?? null;

        abort_unless(is_string($key) && $this->draft()->tree->has($key), 404);

        return $this->draft()->tree->get($key);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function parentKey(array $arguments): ?string
    {
        $parent = $arguments['parent'] ?? null;

        if (! is_string($parent) || $parent === '') {
            return null;
        }

        abort_unless($this->draft()->tree->has($parent), 404);

        return $parent;
    }

    protected function nodeLabel(string $key): string
    {
        $tree = $this->draft()->tree;

        if (! $tree->has($key)) {
            return '';
        }

        return $this->displayLabels($tree)[$key] ?? $key;
    }

    protected function can(string $ability, ?string $placement = null): bool
    {
        return static::authorizer()->can($ability, $placement ?? $this->placement);
    }

    protected function drafts(): MenuDraftManager
    {
        return app(MenuDraftManager::class);
    }

    protected function editor(): MenuEditor
    {
        return app(MenuEditor::class);
    }

    /**
     * Shows field violations inside the open form and everything else as a
     * notification, then keeps the modal open.
     */
    protected function reportFormViolations(InvalidMenuTree $exception, int $index): never
    {
        $unmapped = [];

        foreach ($exception->violations as $violation) {
            if ($violation->field !== null && $violation->field !== 'type') {
                $this->addError("mountedActions.{$index}.data.{$violation->field}", $violation->message);
            } else {
                $unmapped[] = $violation;
            }
        }

        if ($unmapped !== []) {
            $this->notifyInvalid(new InvalidMenuTree($unmapped));
        }

        throw new Halt;
    }

    protected function notifyInvalid(InvalidMenuTree $exception): void
    {
        $labels = $this->displayLabels($this->draft()->tree);
        $messages = [];

        foreach (array_slice($exception->violations, 0, 5) as $violation) {
            $prefix = $violation->field !== null && $violation->field !== 'type' && isset($labels[$violation->key ?? ''])
                ? $labels[$violation->key ?? ''].': '
                : '';

            $messages[] = e($prefix.$violation->message);
        }

        Notification::make()
            ->danger()
            ->title(__('menu-builder::menu-builder.notifications.invalid'))
            ->body(implode('<br>', array_unique($messages)))
            ->send();
    }

    protected function translate(string $key): string
    {
        $translation = __("menu-builder::menu-builder.{$key}");

        return is_string($translation) ? $translation : $key;
    }

    protected function notifyUnauthorized(): void
    {
        Notification::make()
            ->danger()
            ->title(__('menu-builder::menu-builder.notifications.unauthorized'))
            ->send();
    }
}
