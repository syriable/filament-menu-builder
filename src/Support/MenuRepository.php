<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Syriable\Filament\Plugins\MenuBuilder\Models\MenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Reads persisted (published) menus, with an optional per-placement cache.
 */
class MenuRepository
{
    public function __construct(protected readonly CacheFactory $cache) {}

    /**
     * @return class-string<MenuItem>
     */
    public function modelClass(): string
    {
        /** @var class-string<MenuItem> */
        return config('menu-builder.model', MenuItem::class);
    }

    /**
     * @return Builder<MenuItem>
     */
    public function query(): Builder
    {
        return $this->modelClass()::query();
    }

    public function connection(): Connection
    {
        /** @var Connection */
        return $this->query()->getConnection();
    }

    /**
     * Loads the persisted tree of a placement straight from the database.
     */
    public function load(string $placement, bool $lock = false): MenuTree
    {
        $query = $this->query()->where('placement', $placement);

        if ($lock) {
            $query->lockForUpdate();
        }

        return MenuTree::fromModels($placement, $query->get());
    }

    /**
     * The published tree as shown on the frontend, cached when enabled.
     */
    public function published(string $placement): MenuTree
    {
        if (! $this->cacheEnabled()) {
            return $this->load($placement);
        }

        $ttl = config('menu-builder.cache.ttl');
        $resolve = fn (): array => $this->load($placement)->toArray();

        /** @var array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>} $cached */
        $cached = is_numeric($ttl)
            ? $this->store()->remember($this->cacheKey($placement), (int) $ttl, $resolve)
            : $this->store()->rememberForever($this->cacheKey($placement), $resolve);

        return MenuTree::fromArray($cached);
    }

    public function forget(string $placement): void
    {
        $this->store()->forget($this->cacheKey($placement));
    }

    public function cacheKey(string $placement): string
    {
        return config()->string('menu-builder.cache.prefix', 'menu-builder').'.'.$placement;
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('menu-builder.cache.enabled', true);
    }

    protected function store(): CacheRepository
    {
        $store = config('menu-builder.cache.store');

        return $this->cache->store(is_string($store) ? $store : null);
    }
}
