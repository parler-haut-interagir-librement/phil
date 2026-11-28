<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Exception;

use InvalidArgumentException;

/**
 * A rule out of its bounds: a weight, a threshold, a limit or a window.
 */
final class InvalidRule extends InvalidArgumentException implements RelatedContentException
{
    public static function outOfRange(string $rule, float|int $value, float|int $min, float|int $max): self
    {
        $shown = is_nan($value) ? 'NAN' : $value;

        return new self(\sprintf('%s is %s; it must lie between %s and %s.', $rule, $shown, $min, $max));
    }

    public static function emptyName(): self
    {
        return new self('An index needs a name.');
    }
}
