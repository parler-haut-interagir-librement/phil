<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\Formula;

use DateInterval;
use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;

/**
 * Dead stock: a SKU with units available that has not sold for a number of days, or never sold.
 *
 * The moments are compared by `<` and `>` alone: a last sale exactly N days ago is dormant, one a second later is not.
 */
final class Dormancy
{
    /** The days without a sale after which stock is dead when nothing else is configured. */
    public const int DEFAULT_DAYS = 90;

    /** The most days a threshold counts: ten years. */
    public const int MAX_DAYS = 3650;

    /**
     * @throws InvalidFigure when the days are out of 1 to 3650
     */
    public static function isDormant(StockFigure $figure, DateTimeImmutable $now, int $days): bool
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw InvalidFigure::because(\sprintf('dead stock counts from 1 to %d days without a sale', self::MAX_DAYS));
        }

        if ($figure->available < 1) {
            return false;
        }

        return !$figure->lastSoldAt instanceof DateTimeImmutable
            || $figure->lastSoldAt <= $now->sub(new DateInterval(\sprintf('P%dD', $days)));
    }

    /**
     * The whole days from the last sale to now, null when it never sold; zero for a sale dated after now.
     */
    public static function daysWithoutSale(?DateTimeImmutable $lastSoldAt, DateTimeImmutable $now): ?int
    {
        if (!$lastSoldAt instanceof DateTimeImmutable) {
            return null;
        }

        $since = $lastSoldAt->diff($now);

        // A sale dated after now, its interval inverted, is no day without a sale.
        return 1 === $since->invert ? 0 : $since->days;
    }
}
