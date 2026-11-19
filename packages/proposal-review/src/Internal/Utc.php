<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Instants kept in UTC, whatever zone they came in, so that stores and comparisons never depend on one.
 *
 * @internal
 */
final class Utc
{
    public static function of(DateTimeInterface $instant): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Whether the first instant comes strictly before the second, to the microsecond.
     */
    public static function before(DateTimeInterface $first, DateTimeInterface $second): bool
    {
        return $first < $second;
    }
}
