<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Exception;

use RuntimeException;

/**
 * A content larger than the policy allows, refused before it costs more: too many bytes, elements nested too deep, or
 * too many elements or blocks.
 */
final class LimitExceeded extends RuntimeException implements AccessibilityCheckException
{
    public static function size(int $bytes, int $limit): self
    {
        return new self(\sprintf('The content holds %d bytes; at most %d are checked.', $bytes, $limit));
    }

    public static function depth(int $limit): self
    {
        return new self(\sprintf('The content nests deeper than %d levels.', $limit));
    }

    public static function count(int $limit): self
    {
        return new self(\sprintf('The content holds more than %d elements or blocks.', $limit));
    }
}
