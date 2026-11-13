<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * An origin, a key, an embed or its claims that the bundle refuses.
 */
final class InvalidEmbed extends InvalidArgumentException implements EmbedException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
