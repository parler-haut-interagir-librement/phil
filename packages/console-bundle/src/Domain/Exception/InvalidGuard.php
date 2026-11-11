<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A guard attribute declared without an environment it needs, or with a name no environment can have.
 */
final class InvalidGuard extends InvalidArgumentException implements ConsoleException
{
    /**
     * @param class-string $attribute
     */
    public static function withoutEnvironment(string $attribute): self
    {
        return new self(\sprintf('The attribute "%s" needs at least one environment.', $attribute));
    }

    /**
     * @param class-string $attribute
     */
    public static function withInvalidEnvironment(string $attribute, string $environment): self
    {
        return new self(\sprintf('The attribute "%s" names the environment "%s": a name is not empty and holds no space.', $attribute, $environment));
    }
}
