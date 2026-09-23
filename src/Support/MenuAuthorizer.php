<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Asks the host application's policy for the menu item model whether a
 * user may perform an ability on a placement.
 *
 * Abilities: viewAny, create, update, reorder, delete, publish. The policy
 * methods receive the user and the placement key:
 *
 *     public function publish(User $user, string $placement): bool
 *
 * When no policy is registered, every user that can access the Filament
 * panel may manage menus, matching Filament's default behavior.
 */
final readonly class MenuAuthorizer
{
    public const string VIEW_ANY = 'viewAny';

    public const string CREATE = 'create';

    public const string UPDATE = 'update';

    public const string REORDER = 'reorder';

    public const string DELETE = 'delete';

    public const string PUBLISH = 'publish';

    public function __construct(
        private Gate $gate,
        private MenuRepository $repository,
    ) {}

    public function can(string $ability, string $placement, ?Authenticatable $user = null): bool
    {
        $model = $this->repository->modelClass();

        if ($this->gate->getPolicyFor($model) === null) {
            return true;
        }

        $gate = $user === null ? $this->gate : $this->gate->forUser($user);

        return $gate->allows($ability, [$model, $placement]);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(string $ability, string $placement, ?Authenticatable $user = null): void
    {
        if (! $this->can($ability, $placement, $user)) {
            throw new AuthorizationException(__('menu-builder::menu-builder.notifications.unauthorized'));
        }
    }
}
