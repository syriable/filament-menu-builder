<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder\Exceptions;

use Syriable\Filament\Plugins\MenuBuilder\Tree\TreeViolation;

final class InvalidMenuTree extends MenuBuilderException
{
    /**
     * @param  list<TreeViolation>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct($violations[0]->message ?? 'The menu tree is invalid.');
    }

    public static function because(string $message, ?string $key = null, ?string $field = null): self
    {
        return new self([new TreeViolation($message, $key, $field)]);
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_values(array_unique(array_map(
            static fn (TreeViolation $violation): string => $violation->message,
            $this->violations,
        )));
    }

    /**
     * Field errors of one node, keyed by field name.
     *
     * @return array<string, list<string>>
     */
    public function errorsFor(string $key): array
    {
        $errors = [];

        foreach ($this->violations as $violation) {
            if ($violation->key === $key && $violation->field !== null) {
                $errors[$violation->field][] = $violation->message;
            }
        }

        return $errors;
    }
}
