<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Exception;

use InvalidArgumentException;
use Phil\ModerationBundle\Domain\Exception\ModerationException;

/**
 * No package registered a target of that type, or the target does not exist.
 */
final class UnknownTarget extends InvalidArgumentException implements ModerationException
{
    public static function ofType(string $type): self
    {
        return new self(\sprintf('No moderation target is registered for the type "%s".', $type));
    }

    public static function missing(string $type, string $id): self
    {
        return new self(\sprintf('The target "%s" of type "%s" does not exist.', $id, $type));
    }
}
