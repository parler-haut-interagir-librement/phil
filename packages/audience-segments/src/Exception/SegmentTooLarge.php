<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Exception;

use RuntimeException;

/**
 * An audience larger than the bound of a full computation: freezing or recalculating the segment would read more
 * members than `Segments` allows.
 */
final class SegmentTooLarge extends RuntimeException implements AudienceSegmentsException
{
    public static function members(string $segment, int $max): self
    {
        return new self(\sprintf('Computing the segment "%s" reads more than %d members.', $segment, $max));
    }
}
