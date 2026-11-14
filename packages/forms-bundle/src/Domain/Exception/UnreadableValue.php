<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A sealed value that does not open: altered, moved to another entry or field, or sealed under a key no longer known.
 * The message names the field, never the value.
 */
final class UnreadableValue extends RuntimeException implements FormsException
{
    public static function of(string $context, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The sealed value of %s does not open.', $context), previous: $previous);
    }
}
