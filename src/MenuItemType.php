<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;

/**
 * Describes one kind of menu item: its form fields, validation rules and
 * how its label and URL are resolved on the frontend.
 *
 * Type specific values are stored in the item's `data` attribute; the form
 * fields returned by schema() are bound relative to it.
 *
 * Resolver closures may inject `$item` (MenuNode), `$data` (array) and
 * `$record` (the linked model, for model backed types).
 */
class MenuItemType
{
    use EvaluatesClosures;

    protected ?string $label = null;

    protected ?string $icon = null;

    /** @var array<Component>|Closure */
    protected array|Closure $schema = [];

    /** @var array<string, mixed> */
    protected array $rules = [];

    protected bool $canHaveChildren = true;

    protected bool $hasUrl = true;

    protected ?RenderAs $renderAs = null;

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    protected ?string $titleAttribute = null;

    protected ?Closure $modifyQueryUsing = null;

    protected ?Closure $resolveUrlUsing = null;

    protected ?Closure $resolveLabelUsing = null;

    final public function __construct(protected string $key)
    {
        $this->evaluationIdentifier = 'type';
    }

    public static function make(string $key): static
    {
        $type = new static($key);
        $type->setUp();

        return $type;
    }

    protected function setUp(): void {}

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Additional Filament form components, bound to the item's `data`.
     *
     * @param  array<Component>|Closure  $components
     */
    public function schema(array|Closure $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * Server side validation rules for the item's `data`. These are enforced
     * by the tree guard for every write path, not only the Filament form.
     *
     * @param  array<string, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function canHaveChildren(bool $condition = true): static
    {
        $this->canHaveChildren = $condition;

        return $this;
    }

    /**
     * Marks the type as a non-link (e.g. a heading or group). Items of such a
     * type are rendered without a URL.
     */
    public function withoutUrl(): static
    {
        $this->hasUrl = false;

        return $this;
    }

    /**
     * Links items of this type to a record of the given model. The record key
     * is stored as `data.record_id`, and a searchable select is added to the
     * form automatically.
     *
     * @param  class-string  $model
     */
    public function model(string $model, ?string $titleAttribute = null, ?Closure $modifyQueryUsing = null): static
    {
        if (! is_a($model, Model::class, allow_string: true)) {
            throw new InvalidArgumentException("[{$model}] is not an Eloquent model.");
        }

        $this->model = $model;
        $this->titleAttribute = $titleAttribute;
        $this->modifyQueryUsing = $modifyQueryUsing;

        return $this;
    }

    /**
     * The element items of this type render as. Defaults to a link for
     * types with a URL and to a heading otherwise.
     */
    public function renderAs(?RenderAs $renderAs): static
    {
        $this->renderAs = $renderAs;

        return $this;
    }

    public function getRenderAs(): RenderAs
    {
        return $this->renderAs ?? ($this->hasUrl ? RenderAs::Link : RenderAs::Heading);
    }

    public function resolveUrlUsing(?Closure $callback): static
    {
        $this->resolveUrlUsing = $callback;

        return $this;
    }

    public function resolveLabelUsing(?Closure $callback): static
    {
        $this->resolveLabelUsing = $callback;

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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function canHaveChildItems(): bool
    {
        return $this->canHaveChildren;
    }

    public function hasUrl(): bool
    {
        return $this->hasUrl;
    }

    public function isModelBacked(): bool
    {
        return $this->model !== null;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Whether an item may leave its label empty because it can be derived,
     * e.g. from the linked record.
     */
    public function resolvesLabel(): bool
    {
        return $this->resolveLabelUsing !== null || $this->titleAttribute !== null;
    }

    /**
     * @return array<Component>
     */
    public function getFormSchema(): array
    {
        $schema = $this->evaluate($this->schema);

        if ($this->model === null) {
            return $schema;
        }

        return [$this->makeRecordSelect(), ...$schema];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        if ($this->model === null) {
            return $this->rules;
        }

        return ['record_id' => ['required'], ...$this->rules];
    }

    public function getRecordKey(MenuNode $item): int|string|null
    {
        $key = $item->data['record_id'] ?? null;

        return is_int($key) || (is_string($key) && $key !== '') ? $key : null;
    }

    /**
     * Loads the linked records of many items with a single query.
     *
     * @param  array<int|string>  $keys
     * @return array<int|string, Model>
     */
    public function findRecords(array $keys): array
    {
        if ($this->model === null || $keys === []) {
            return [];
        }

        /** @var EloquentCollection<int, Model> $records */
        $records = $this->newRecordQuery()->whereKey(array_values(array_unique($keys)))->get();

        return $records->keyBy(static fn (Model $record): int|string => $record->getKey())->all();
    }

    public function resolveUrl(MenuNode $item, ?Model $record = null): ?string
    {
        if (! $this->hasUrl || $this->resolveUrlUsing === null) {
            return null;
        }

        $url = $this->evaluate($this->resolveUrlUsing, $this->injections($item, $record));

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function resolveLabel(MenuNode $item, ?Model $record = null): string
    {
        if ($item->label !== null) {
            return $item->label;
        }

        if ($this->resolveLabelUsing !== null) {
            $label = $this->evaluate($this->resolveLabelUsing, $this->injections($item, $record));

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        if ($record !== null && $this->titleAttribute !== null) {
            $title = $record->getAttribute($this->titleAttribute);

            if (is_scalar($title) && (string) $title !== '') {
                return (string) $title;
            }
        }

        return $this->getLabel();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    protected function newRecordQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var class-string<Model> $model */
        $model = $this->model;

        $query = $model::query();

        if ($this->modifyQueryUsing !== null) {
            $query = $this->evaluate($this->modifyQueryUsing, ['query' => $query]) ?? $query;
        }

        return $query;
    }

    protected function makeRecordSelect(): Select
    {
        $titleAttribute = $this->titleAttribute;

        return Select::make('record_id')
            ->label($this->getLabel())
            ->required()
            ->searchable()
            ->getSearchResultsUsing(function (?string $search) use ($titleAttribute): array {
                $query = $this->newRecordQuery()->limit(50);

                if ($titleAttribute !== null && filled($search)) {
                    $query->where($titleAttribute, 'like', '%'.$search.'%');
                }

                return $query->get()
                    ->mapWithKeys(fn (Model $record): array => [$record->getKey() => $this->recordTitle($record)])
                    ->all();
            })
            ->getOptionLabelUsing(function (mixed $value): ?string {
                $record = is_int($value) || is_string($value) ? ($this->findRecords([$value])[$value] ?? null) : null;

                return $record === null ? null : $this->recordTitle($record);
            });
    }

    protected function recordTitle(Model $record): string
    {
        $title = $this->titleAttribute === null ? null : $record->getAttribute($this->titleAttribute);

        return is_scalar($title) ? (string) $title : (string) $record->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    protected function injections(MenuNode $item, ?Model $record): array
    {
        return ['item' => $item, 'data' => $item->data, 'record' => $record];
    }
}
