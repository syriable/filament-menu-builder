<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Drafts;

use Illuminate\Contracts\Cache\Repository;
use Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore;

/**
 * Keeps drafts server side in Laravel's cache, so the browser never holds
 * (or can tamper with) the working copy and Livewire payloads stay small.
 */
final readonly class CacheDraftStore implements DraftStore
{
    public function __construct(
        private Repository $cache,
        private int $ttl,
        private string $prefix = 'menu-builder.draft.',
    ) {}

    public function find(string $id): ?MenuDraft
    {
        $payload = $this->cache->get($this->prefix.$id);

        if (! is_array($payload)) {
            return null;
        }

        /** @var array{id: string, base: string, tree: array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>}} $payload */
        return MenuDraft::fromArray($payload);
    }

    public function put(MenuDraft $draft): void
    {
        $this->cache->put($this->prefix.$draft->id, $draft->toArray(), $this->ttl);
    }

    public function forget(string $id): void
    {
        $this->cache->forget($this->prefix.$id);
    }
}
