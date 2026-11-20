<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use Phil\AiBundle\Domain\Exception\AiException;
use RuntimeException;

/**
 * A task that must run on the person's own key, and no key of theirs opens the platform.
 */
final class NoUserKey extends RuntimeException implements AiException
{
    public static function for(string $platform): self
    {
        return new self(\sprintf('The task runs only on the person\'s own key, and they have none for the platform "%s".', $platform));
    }
}
