<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Exception;

use InvalidArgumentException;

/**
 * An alphabet, a minimum length or numbers the identifier codec cannot work with.
 */
final class InvalidCodec extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
