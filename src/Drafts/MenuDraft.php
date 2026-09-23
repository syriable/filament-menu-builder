<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Drafts;

use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * A working copy of a placement's tree.
 *
 * The base fingerprint identifies the persisted tree the draft started
 * from. It is used to detect unsaved changes and to refuse publishing over
 * changes somebody else saved in the meantime.
 */
final class MenuDraft
{
    public function __construct(
        public readonly string $id,
        public MenuTree $tree,
        public string $baseFingerprint,
    ) {}

    public function placement(): string
    {
        return $this->tree->placement;
    }

    public function isDirty(): bool
    {
        return $this->tree->fingerprint() !== $this->baseFingerprint;
    }

    /**
     * @return array{id: string, base: string, tree: array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>}}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'base' => $this->baseFingerprint, 'tree' => $this->tree->toArray()];
    }

    /**
     * @param  array{id: string, base: string, tree: array{placement: string, items: list<array{node: array<string, mixed>, parent: string|null}>}}  $array
     */
    public static function fromArray(array $array): self
    {
        return new self($array['id'], MenuTree::fromArray($array['tree']), $array['base']);
    }
}
