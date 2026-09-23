<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver;

/**
 * The route must exist and a URL must be generated from it with the route
 * parameters of the same item, so required parameters cannot be missing.
 */
final class ResolvableRoute implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $resolver = app(UrlResolver::class);
        $route = is_string($value) ? $value : null;

        if (! $resolver->routeExists($route)) {
            $fail(__('menu-builder::menu-builder.validation.route_missing', ['route' => $route ?? '']));

            return;
        }

        $parameters = is_array($this->data['route_parameters'] ?? null) ? $this->data['route_parameters'] : [];

        if ($resolver->route($route, $parameters) === null) {
            $fail(__('menu-builder::menu-builder.validation.route_parameters', ['route' => $route]));
        }
    }
}
