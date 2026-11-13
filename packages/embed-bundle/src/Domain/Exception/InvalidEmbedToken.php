<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A token that is not one the bundle signed, for this embed, still valid: malformed, of another format or algorithm, signed by an unknown key, forged, or expired.
 */
final class InvalidEmbedToken extends InvalidArgumentException implements EmbedException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
