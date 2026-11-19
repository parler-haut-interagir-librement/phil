<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A browser's report the parser cannot read: not a JSON object, or without a message or a page.
 */
final class InvalidBrowserError extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
