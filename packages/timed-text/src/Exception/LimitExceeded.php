<?php

declare(strict_types=1);

namespace Phil\TimedText\Exception;

use RuntimeException;

/**
 * A document larger than the limits allow: refused before it costs more.
 */
final class LimitExceeded extends RuntimeException implements TimedTextException
{
    public static function bytes(int $bytes, int $limit): self
    {
        return new self(\sprintf('The document is %d bytes; the limit is %d.', $bytes, $limit));
    }

    public static function count(string $what, int $limit): self
    {
        return new self(\sprintf('The document holds more than %d %s.', $limit, $what));
    }

    public static function text(?int $line, int $limit): self
    {
        return new self(null === $line
            ? \sprintf('A text is longer than %d bytes.', $limit)
            : \sprintf('Line %d: a text is longer than %d bytes.', $line, $limit));
    }
}
