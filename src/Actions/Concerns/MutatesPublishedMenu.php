<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions\Concerns;

use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishResult;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuEditor;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Loads the persisted tree of a placement, applies one change through the
 * MenuEditor and publishes the result, all inside a single transaction.
 *
 * @property-read MenuEditor $editor
 * @property-read MenuRepository $repository
 * @property-read PublishMenuTree $publisher
 */
trait MutatesPublishedMenu
{
    /**
     * @template TReturn
     *
     * @param  callable(MenuTree): TReturn  $change
     * @return array{0: TReturn, 1: PublishResult}
     */
    protected function mutate(string $placement, callable $change): array
    {
        return $this->repository->connection()->transaction(function () use ($placement, $change): array {
            $tree = $this->repository->load($placement, lock: true);

            $value = $change($tree);

            return [$value, $this->publisher->handle($tree)];
        });
    }

    protected function resolveItem(MenuItem|int $item): MenuItem
    {
        if ($item instanceof MenuItem) {
            return $item;
        }

        return $this->repository->query()->find($item)
            ?? throw InvalidMenuTree::because(__('menu-builder::menu-builder.validation.missing_item', ['item' => $item]));
    }

    protected function keyFor(?int $id, MenuTree $tree): ?string
    {
        if ($id === null) {
            return null;
        }

        return $tree->findById($id)->key
            ?? throw InvalidMenuTree::because(__('menu-builder::menu-builder.validation.missing_parent'));
    }

    protected function freshModel(PublishResult $result, string $key): MenuItem
    {
        /** @var MenuItem */
        return $this->repository->query()->findOrFail($result->ids[$key]);
    }

    protected function keyOf(MenuItem $item): string
    {
        return MenuNode::keyForId($item->id);
    }
}
