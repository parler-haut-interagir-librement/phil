<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * The moments of the model, to the second and in UTC, as the stores keep them: a moment read back is the moment kept.
 */
final readonly class Moment
{
    private function __construct()
    {
    }

    public static function of(DateTimeImmutable $moment): DateTimeImmutable
    {
        return self::fromTimestamp($moment->getTimestamp());
    }

    public static function fromTimestamp(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$timestamp);
    }
}
