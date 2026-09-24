<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Syriable\Filament\Plugins\MenuBuilder\Enums\HttpMethod;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlParameters;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlResolver;

/**
 * The route must exist and a URL must be generated from it with the route
 * parameters of the same item, so required parameters cannot be missing.
 * Placeholders ({user}, {route.user}, ...) must be known; they are replaced
 * with a sample value for the check, since their real value depends on the
 * visitor.
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

        $method = HttpMethod::fromData($this->data['method'] ?? null);

        if (! $resolver->routeAcceptsMethod($route, $method)) {
            $fail(__('menu-builder::menu-builder.validation.route_method', ['route' => $route, 'method' => $method->value]));

            return;
        }

        $placeholders = app(UrlParameters::class);
        $parameters = is_array($this->data['route_parameters'] ?? null) ? $this->data['route_parameters'] : [];

        foreach ($parameters as $key => $parameter) {
            if (! is_string($parameter)) {
                continue;
            }

            foreach ($placeholders->unknown($parameter) as $unknown) {
                $fail(__('menu-builder::menu-builder.validation.unknown_placeholder', ['placeholder' => '{'.$unknown.'}']));

                return;
            }

            $parameters[$key] = $placeholders->sample($parameter);
        }

        if ($resolver->route($route, $parameters) === null) {
            $fail(__('menu-builder::menu-builder.validation.route_parameters', ['route' => $route]));
        }
    }
}
