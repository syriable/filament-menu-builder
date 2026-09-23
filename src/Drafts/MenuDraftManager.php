<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Drafts;

use Illuminate\Support\Str;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishResult;
use Syriable\Filament\Plugins\MenuBuilder\Contracts\DraftStore;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\DraftNotFound;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\InvalidMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Exceptions\StaleMenu;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;

/**
 * Lifecycle of drafts: start from the persisted tree, keep changes,
 * publish them atomically or discard them.
 */
final readonly class MenuDraftManager
{
    public function __construct(
        private DraftStore $store,
        private MenuRepository $repository,
        private PublishMenuTree $publisher,
    ) {}

    public function start(string $placement): MenuDraft
    {
        $tree = $this->repository->load($placement);
        $draft = new MenuDraft((string) Str::uuid(), $tree, $tree->fingerprint());

        $this->store->put($draft);

        return $draft;
    }

    /**
     * @throws DraftNotFound
     */
    public function find(string $id): MenuDraft
    {
        return $this->store->find($id) ?? throw DraftNotFound::make($id);
    }

    public function save(MenuDraft $draft): void
    {
        $this->store->put($draft);
    }

    /**
     * Validates and persists the complete draft. On success the draft is
     * rebased on the freshly published tree.
     *
     * @throws InvalidMenuTree
     * @throws StaleMenu
     */
    public function publish(MenuDraft $draft): PublishResult
    {
        $result = $this->publisher->handle($draft->tree, $draft->baseFingerprint);

        $draft->tree = $result->tree;
        $draft->baseFingerprint = $result->tree->fingerprint();

        $this->store->put($draft);

        return $result;
    }

    /**
     * Throws the working copy away and reloads the persisted tree from the
     * database, which is always authoritative.
     */
    public function discard(MenuDraft $draft): MenuDraft
    {
        $tree = $this->repository->load($draft->placement());

        $draft->tree = $tree;
        $draft->baseFingerprint = $tree->fingerprint();

        $this->store->put($draft);

        return $draft;
    }

    public function forget(MenuDraft $draft): void
    {
        $this->store->forget($draft->id);
    }
}
