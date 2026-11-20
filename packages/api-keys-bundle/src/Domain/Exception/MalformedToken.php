<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A text that is not a token of the configured prefix: another length, another prefix, a character no token holds,
 * a checksum that does not match. The message says which, never the text itself.
 */
final class MalformedToken extends InvalidArgumentException implements ApiKeysException
{
    public static function because(string $reason): self
    {
        return new self('The text is not a token: '.$reason.'.');
    }
}
