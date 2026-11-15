<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Exception;

use Phil\ModerationBundle\Domain\Exception\ModerationException;
use RuntimeException;

/**
 * The current member may not do this: they are not a moderator, their role does not cover the target, they are not
 * trusted to hide, or the reason is not in the catalogue.
 */
final class ModerationRefused extends RuntimeException implements ModerationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
