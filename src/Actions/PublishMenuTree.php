<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Syriable\Filament\Plugins\MenuBuilder\Events\MenuPublished;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\StaleMenu;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTreeGuard;

/**
 * Persists a complete tree for one placement.
 *
 * The whole tree is validated first and then written in one transaction:
 * new items are inserted, changed items updated (parents before children)
 * and items missing from the tree deleted (deepest first). Either the
 * complete tree is persisted or nothing is.
 */
final readonly class PublishMenuTree
{
    public function __construct(
        private MenuTreeGuard $guard,
        private MenuRepository $repository,
        private Dispatcher $events,
    ) {}

    /**
     * @param  string|null  $expectedFingerprint  Fingerprint of the persisted tree the change was based on.
     *                                            When given and the persisted tree changed since, nothing is written.
     *
     * @throws InvalidMenuTree
     * @throws StaleMenu
     */
    public function handle(MenuTree $tree, ?string $expectedFingerprint = null): PublishResult
    {
        $this->guard->assertValid($tree);

        $result = $this->repository->connection()->transaction(function () use ($tree, $expectedFingerprint): PublishResult {
            /** @var Collection<int, MenuItem> $existing */
            $existing = $this->repository->query()
                ->where('placement', $tree->placement)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($expectedFingerprint !== null && MenuTree::fromModels($tree->placement, $existing)->fingerprint() !== $expectedFingerprint) {
                throw StaleMenu::make($tree->placement);
            }

            [$ids, $created, $updated] = $this->write($tree, $existing);

            $deleted = $this->deleteMissing($existing, $ids);

            return new PublishResult($this->repository->load($tree->placement), $ids, $created, $updated, $deleted);
        });

        $this->repository->forget($tree->placement);

        $this->events->dispatch(new MenuPublished($tree->placement, $result));

        return $result;
    }

    /**
     * @param  Collection<int, MenuItem>  $existing
     * @return array{0: array<string, int>, 1: int, 2: int}
     */
    private function write(MenuTree $tree, Collection $existing): array
    {
        $modelClass = $this->repository->modelClass();
        $ids = [];
        $positions = array_flip($tree->roots());
        $created = 0;
        $updated = 0;

        foreach ($tree->walk() as $key => $depth) {
            $node = $tree->get($key);
            $parentKey = $tree->parentOf($key);

            $attributes = [
                ...$node->attributes(),
                'placement' => $tree->placement,
                'parent_id' => $parentKey === null ? null : $ids[$parentKey],
                'sort_order' => $positions[$key],
            ];

            if ($node->id === null) {
                $item = new $modelClass;
                $item->fill($attributes)->save();
                $created++;
            } else {
                $item = $existing->get($node->id) ?? throw InvalidMenuTree::because(
                    __('menu-builder::menu-builder.validation.missing_item', ['item' => $node->label ?? $node->key]),
                    $key,
                );

                $item->fill($attributes);

                if ($item->isDirty()) {
                    $item->save();
                    $updated++;
                }
            }

            $ids[$key] = $item->id;
            $positions += array_flip($tree->childrenOf($key));
        }

        return [$ids, $created, $updated];
    }

    /**
     * Deletes the persisted items that are no longer part of the tree,
     * children before their parents so the restrictive foreign key holds.
     *
     * @param  Collection<int, MenuItem>  $existing
     * @param  array<string, int>  $keptIds
     */
    private function deleteMissing(Collection $existing, array $keptIds): int
    {
        $kept = array_flip($keptIds);
        $missing = $existing->reject(static fn (MenuItem $item): bool => isset($kept[$item->id]));

        if ($missing->isEmpty()) {
            return 0;
        }

        $depths = [];
        $depthOf = static function (MenuItem $item) use (&$depthOf, &$depths, $existing): int {
            if (isset($depths[$item->id])) {
                return $depths[$item->id];
            }

            $depths[$item->id] = 0;
            $parent = $item->parent_id === null ? null : $existing->get($item->parent_id);

            return $depths[$item->id] = $parent === null ? 1 : $depthOf($parent) + 1;
        };

        $missing
            ->groupBy(static fn (MenuItem $item): int => $depthOf($item))
            ->sortKeysDesc()
            ->each(fn (Collection $items) => $this->repository->query()->whereKey($items->modelKeys())->delete());

        return $missing->count();
    }
}
