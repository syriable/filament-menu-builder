<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Contracts;

use Syriable\Filament\Plugins\MenuBuilder\Drafts\MenuDraft;

/**
 * Storage for unpublished working copies.
 *
 * Bind your own implementation to keep drafts in the database, share them
 * between administrators or add revisions.
 */
interface DraftStore
{
    public function find(string $id): ?MenuDraft;

    public function put(MenuDraft $draft): void;

    public function forget(string $id): void;
}
