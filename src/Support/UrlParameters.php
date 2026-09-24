<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Support;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Stringable;

/**
 * Placeholders that make link URLs and route parameters dynamic, resolved
 * for every visitor when the menu is built:
 *
 * - `{user}` / `{user.attribute}`: the visitor (route key, or an attribute)
 * - `{route.name}`: a parameter of the current route (bound models give
 *   their route key), e.g. the profile being viewed
 * - `{query.name}`: a query string value of the current request
 * - `{name}` / `{name.path}`: parameters registered with
 *   Menu::registerUrlParameter()
 *
 * A placeholder that cannot be resolved (a guest, a missing value) makes the
 * whole URL unresolvable, and the item is left out of the menu.
 */
final class UrlParameters
{
    public const string PATTERN = '/\{([a-z][a-z0-9_]*)(?:\.([a-z0-9_.-]+))?\}/i';

    /** @var list<string> */
    public const array BUILT_IN = ['user', 'route', 'query'];

    /** @var array<string, Closure> */
    private array $parameters = [];

    private ?Authenticatable $user = null;

    private bool $hasUser = false;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Registers a placeholder. The resolver may inject `$user`
     * (?Authenticatable), `$request` (Request) and `$path` (the part after
     * the dot, or null) and returns a scalar, a Stringable, a routable model,
     * or null when there is no value.
     */
    public function register(string $name, Closure $resolver): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $name) !== 1) {
            throw new InvalidArgumentException("[{$name}] is not a valid URL parameter name.");
        }

        $this->parameters[strtolower($name)] = $resolver;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([...self::BUILT_IN, ...array_keys($this->parameters)]));
    }

    public function has(string $name): bool
    {
        return in_array(strtolower($name), $this->names(), true);
    }

    /**
     * Resolves placeholders for the given visitor while the callback runs,
     * instead of the authenticated user.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function forUser(?Authenticatable $user, Closure $callback): mixed
    {
        [$previousUser, $previousHasUser] = [$this->user, $this->hasUser];
        [$this->user, $this->hasUser] = [$user, true];

        try {
            return $callback();
        } finally {
            [$this->user, $this->hasUser] = [$previousUser, $previousHasUser];
        }
    }

    public function containsPlaceholders(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Replaces every placeholder in the value. Returns null when one of
     * them has no value. Unknown placeholders are left as they are.
     *
     * @param  bool  $encode  URL-encode the values, for placeholders inside a URL.
     */
    public function replace(string $value, bool $encode = false): ?string
    {
        $unresolved = false;

        $result = preg_replace_callback(self::PATTERN, function (array $match) use ($encode, &$unresolved): string {
            if (! $this->has($match[1])) {
                return $match[0];
            }

            $resolved = $this->resolve($match[1], ($match[2] ?? '') === '' ? null : $match[2]);

            if ($resolved === null) {
                $unresolved = true;

                return '';
            }

            return $encode ? rawurlencode($resolved) : $resolved;
        }, $value);

        return $unresolved || $result === null ? null : $result;
    }

    /**
     * Replaces known placeholders with a sample value, to validate a route
     * whose parameters are only known at render time.
     */
    public function sample(string $value): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => $this->has($match[1]) ? '1' : $match[0],
            $value,
        );
    }

    /**
     * Placeholders in the value that are not registered.
     *
     * @return list<string>
     */
    public function unknown(string $value): array
    {
        preg_match_all(self::PATTERN, $value, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $name): bool => ! $this->has($name),
        )));
    }

    private function resolve(string $name, ?string $path): ?string
    {
        $name = strtolower($name);
        $user = $this->hasUser ? $this->user : $this->container->make(AuthFactory::class)->guard()->user();
        $request = $this->container->make(Request::class);

        $value = match (true) {
            isset($this->parameters[$name]) => $this->container->call($this->parameters[$name], [
                'user' => $user,
                'request' => $request,
                'path' => $path,
            ]),
            $name === 'user' => $this->userValue($user, $path),
            $name === 'route' => $path === null ? null : $request->route($path),
            $name === 'query' => $path === null ? null : $request->query($path),
            default => null,
        };

        return $this->stringify($value);
    }

    private function userValue(?Authenticatable $user, ?string $attribute): mixed
    {
        if ($user === null) {
            return null;
        }

        if ($attribute === null) {
            return $user instanceof UrlRoutable ? $user->getRouteKey() : $user->getAuthIdentifier();
        }

        // Hidden attributes (password, remember_token, ...) are never exposed in URLs.
        if ($user instanceof Model) {
            return in_array($attribute, $user->getHidden(), true) ? null : $user->getAttribute($attribute);
        }

        return null;
    }

    private function stringify(mixed $value): ?string
    {
        $value = match (true) {
            $value instanceof UrlRoutable => $value->getRouteKey(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof Stringable => (string) $value,
            default => $value,
        };

        if (is_bool($value) || ! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
