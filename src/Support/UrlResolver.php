<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Central place to turn link data into a URL.
 *
 * Link data is either `['link_type' => 'url', 'url' => '...']` or
 * `['link_type' => 'route', 'route' => 'name', 'route_parameters' => [...]]`.
 *
 * URLs and route parameter values may contain placeholders such as
 * `{user}` or `{route.user}` (see UrlParameters), resolved for the current
 * visitor. A placeholder without a value resolves the whole link to null.
 */
class UrlResolver
{
    public const string TYPE_URL = 'url';

    public const string TYPE_ROUTE = 'route';

    public function __construct(
        protected readonly Router $router,
        protected readonly UrlGenerator $url,
        protected readonly UrlParameters $parameters,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(array $data): ?string
    {
        return match ($data['link_type'] ?? self::TYPE_URL) {
            self::TYPE_ROUTE => $this->route(
                is_string($data['route'] ?? null) ? $data['route'] : null,
                is_array($data['route_parameters'] ?? null) ? $data['route_parameters'] : [],
            ),
            default => $this->url(is_string($data['url'] ?? null) ? $data['url'] : null),
        };
    }

    /**
     * Any URL an administrator enters is accepted as-is: paths, absolute
     * URLs, fragments, mailto: and tel: links, ...
     */
    public function url(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url !== '' && $this->parameters->containsPlaceholders($url)) {
            $url = (string) $this->parameters->replace($url, encode: true);
        }

        return $url === '' ? null : $url;
    }

    /**
     * Resolves a named route. Missing routes and missing required parameters
     * resolve to null instead of throwing, so a stale menu item never breaks
     * the page it is rendered on.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    public function route(?string $name, array $parameters = []): ?string
    {
        if ($name === null || $name === '' || ! $this->router->has($name)) {
            return null;
        }

        $parameters = array_filter(
            $parameters,
            static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '',
        );

        foreach ($parameters as $key => $value) {
            if (! is_string($value) || ! $this->parameters->containsPlaceholders($value)) {
                continue;
            }

            $resolved = $this->parameters->replace($value);

            if ($resolved === null) {
                return null;
            }

            $parameters[$key] = $resolved;
        }

        try {
            return $this->url->route($name, $parameters);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    public function routeExists(?string $name): bool
    {
        return $name !== null && $name !== '' && $this->router->has($name);
    }

    /**
     * Named GET routes that can be offered to administrators.
     *
     * @param  list<string>  $exclude  Route name patterns to hide.
     * @return array<string, string> Route name => label.
     */
    public function selectableRoutes(array $exclude = []): array
    {
        $routes = [];

        /** @var Route $route */
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true) || Str::is($exclude, $name)) {
                continue;
            }

            $routes[$name] = $name.' ('.'/'.ltrim($route->uri(), '/').')';
        }

        ksort($routes);

        return $routes;
    }

    /**
     * @return list<string>
     */
    public function routeParameterNames(?string $name): array
    {
        if (! $this->routeExists($name)) {
            return [];
        }

        return array_values($this->router->getRoutes()->getByName((string) $name)?->parameterNames() ?? []);
    }
}
