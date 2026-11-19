<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Exception;

/**
 * No conferencing server of the configuration has this name.
 */
final class UnknownProvider extends ClassroomException
{
    public static function of(string $provider): self
    {
        return new self(\sprintf('No provider is named "%s".', $provider));
    }
}
