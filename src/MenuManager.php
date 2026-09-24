<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishMenuTree;
use Syriable\Filament\Plugins\MenuBuilder\Actions\PublishResult;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlParameters;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Entry point behind the Menu facade.
 */
final readonly class MenuManager
{
    public function __construct(
        private MenuRegistry $registry,
        private MenuRepository $repository,
    ) {}

    public function registerPlacement(MenuPlacement ...$placements): self
    {
        $this->registry->registerPlacement(...$placements);

        return $this;
    }

    public function registerItemType(MenuItemType ...$types): self
    {
        $this->registry->registerItemType(...$types);

        return $this;
    }

    public function registerVisibility(MenuVisibility ...$visibilities): self
    {
        $this->registry->registerVisibility(...$visibilities);

        return $this;
    }

    /**
     * Registers a `{name}` placeholder for link URLs and route parameters.
     * The resolver may inject `$user`, `$request` and `$path` and returns
     * the value, or null to hide links that use it.
     */
    public function registerUrlParameter(string $name, Closure $resolver): self
    {
        app(UrlParameters::class)->register($name, $resolver);

        return $this;
    }

    /**
     * @return array<string, MenuPlacement>
     */
    public function placements(): array
    {
        return $this->registry->placements();
    }

    public function placement(string $key): MenuPlacement
    {
        return $this->registry->placement($key);
    }

    /**
     * @return array<string, MenuItemType>
     */
    public function itemTypes(): array
    {
        return $this->registry->itemTypes();
    }

    public function itemType(string $key): MenuItemType
    {
        return $this->registry->itemType($key);
    }

    /**
     * Builds the published menu of a placement for the frontend.
     *
     * @return Collection<int, ResolvedMenuItem>
     */
    public function build(string $placement, ?Authenticatable $user = null, ?string $currentUrl = null, ?string $locale = null): Collection
    {
        return app(MenuBuilder::class)->build($placement, $user, $currentUrl, $locale);
    }

    /**
     * The published tree of a placement, unresolved.
     */
    public function tree(string $placement): MenuTree
    {
        return $this->repository->published($placement);
    }

    /**
     * Replaces the complete published menu of a placement, e.g. in a seeder.
     * The items are validated against the placement rules first.
     *
     * @param  list<array<string, mixed>>  $items  Nested items with an optional `children` key.
     */
    public function sync(string $placement, array $items): PublishResult
    {
        $this->registry->placement($placement);

        return app(PublishMenuTree::class)->handle(MenuTree::fromNestedArray($placement, $items));
    }

    public function flushCache(string $placement): void
    {
        $this->repository->forget($placement);
    }

    public function registry(): MenuRegistry
    {
        return $this->registry;
    }
}
