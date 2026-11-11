<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Exception;

use InvalidArgumentException;

/**
 * A `Policy` was asked to allow what the library never allows, or was given a limit that cannot hold.
 */
final class InvalidPolicy extends InvalidArgumentException implements CssSanitizerException
{
    public static function deniedProperty(string $property): self
    {
        return new self(\sprintf('The property "%s" can run code or load a binding; it is never allowed.', $property));
    }

    public static function deniedFunction(string $function): self
    {
        return new self(\sprintf('The function "%s()" can run code or load a resource; it is never allowed.', $function));
    }

    public static function limit(string $name, int $value): self
    {
        return new self(\sprintf('The %s must be at least 1; got %d.', $name, $value));
    }

    public static function negative(string $name, int $value): self
    {
        return new self(\sprintf('The %s cannot be negative; got %d.', $name, $value));
    }
}
