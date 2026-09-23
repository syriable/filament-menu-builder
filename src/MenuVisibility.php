<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuNode;

/**
 * A named rule that decides whether an item is shown to the current visitor.
 */
final readonly class MenuVisibility
{
    public const string EVERYONE = 'everyone';

    public const string GUESTS = 'guests';

    public const string AUTHENTICATED = 'authenticated';

    /**
     * @param  string|Closure(): string  $label
     * @param  Closure(?Authenticatable, MenuNode): bool  $callback
     */
    public function __construct(
        public string $key,
        public string|Closure $label,
        public Closure $callback,
    ) {}

    /**
     * @param  string|Closure(): string  $label  A closure is resolved lazily, e.g. to translate per request.
     * @param  Closure(?Authenticatable, MenuNode): bool  $callback
     */
    public static function make(string $key, string|Closure $label, Closure $callback): self
    {
        return new self($key, $label, $callback);
    }

    public function getLabel(): string
    {
        return is_string($this->label) ? $this->label : ($this->label)();
    }

    public function allows(?Authenticatable $user, MenuNode $item): bool
    {
        return (bool) ($this->callback)($user, $item);
    }
}
