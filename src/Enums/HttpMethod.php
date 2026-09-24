<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Enums;

/**
 * The HTTP method a link uses. GET renders a plain link; any other method
 * renders a button that submits a small form (with the CSRF token and, for
 * PUT, PATCH and DELETE, Laravel's method spoofing field).
 */
enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    public static function fromData(mixed $value): self
    {
        return (is_string($value) ? self::tryFrom(strtoupper($value)) : null) ?? self::Get;
    }

    /**
     * Whether the link needs a form to be followed.
     */
    public function needsForm(): bool
    {
        return $this !== self::Get;
    }

    /**
     * The method of the <form> element: browsers only send GET and POST.
     */
    public function formMethod(): string
    {
        return $this === self::Get ? 'GET' : 'POST';
    }

    /**
     * The value of Laravel's `_method` field, for methods browsers cannot send.
     */
    public function spoofed(): ?string
    {
        return in_array($this, [self::Put, self::Patch, self::Delete], true) ? $this->value : null;
    }
}
