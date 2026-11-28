<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Exception;

use LogicException;

/**
 * A port implementation that breaks its contract: a page out of ascending order, larger than asked, not after its
 * cursor, or holding an identifier out of form. The library stops rather than loop or skip members.
 */
final class PortContractViolated extends LogicException implements AudienceSegmentsException
{
    public static function order(string $port, string $id): self
    {
        return new self(\sprintf('%s gave "%s" out of ascending byte order, or at or before its cursor.', $port, $id));
    }

    public static function size(string $port, int $count, int $limit): self
    {
        return new self(\sprintf('%s gave %d members when %d were asked.', $port, $count, $limit));
    }
}
