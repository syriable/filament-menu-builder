<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * Denies the abilities listed as "ability:placement".
 */
class MenuItemPolicy
{
    /** @var list<string> */
    public static array $denied = [];

    public function viewAny(User $user, string $placement): bool
    {
        return $this->allows('viewAny', $placement);
    }

    public function create(User $user, string $placement): bool
    {
        return $this->allows('create', $placement);
    }

    public function update(User $user, string $placement): bool
    {
        return $this->allows('update', $placement);
    }

    public function reorder(User $user, string $placement): bool
    {
        return $this->allows('reorder', $placement);
    }

    public function delete(User $user, string $placement): bool
    {
        return $this->allows('delete', $placement);
    }

    public function publish(User $user, string $placement): bool
    {
        return $this->allows('publish', $placement);
    }

    private function allows(string $ability, string $placement): bool
    {
        return ! in_array("{$ability}:{$placement}", self::$denied, true);
    }
}
