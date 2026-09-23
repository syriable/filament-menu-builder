<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Actions;

use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

final readonly class PublishResult
{
    /**
     * @param  array<string, int>  $ids  Database id of every published item, by the key it had in the published tree.
     */
    public function __construct(
        public MenuTree $tree,
        public array $ids,
        public int $created,
        public int $updated,
        public int $deleted,
    ) {}

    public function hasChanges(): bool
    {
        return $this->created + $this->updated + $this->deleted > 0;
    }
}
