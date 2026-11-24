<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Fixture;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * Lines and periods for the tests: November 2026, in UTC.
 */
final class Lines
{
    public static function period(): Period
    {
        return new Period(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));
    }

    public static function money(int $minorUnits, string $currency = 'CAD'): Money
    {
        return Money::of($minorUnits, Currency::of($currency));
    }

    public static function sold(
        string $sku = 'BOOK-1',
        int $quantity = 1,
        int $gross = 1000,
        int $discounts = 0,
        string $currency = 'CAD',
        ?string $category = 'books',
        ?string $channel = 'store',
        ?string $location = 'main',
        ?string $cashier = 'ann',
        string $at = '2026-11-10T12:00:00Z',
        ?string $product = null,
    ): SoldLine {
        return new SoldLine($sku, $product ?? 'Product '.$sku, $category, $channel, $location, $cashier, $quantity, self::money($gross, $currency), self::money($discounts, $currency), new DateTimeImmutable($at));
    }

    public static function returned(
        string $sku = 'BOOK-1',
        int $quantity = 1,
        int $amount = 1000,
        string $currency = 'CAD',
        ?string $category = 'books',
        ?string $channel = 'store',
        ?string $location = 'main',
        ?string $cashier = 'ann',
        string $at = '2026-11-12T12:00:00Z',
    ): ReturnedLine {
        return new ReturnedLine($sku, 'Product '.$sku, $category, $channel, $location, $cashier, $quantity, self::money($amount, $currency), new DateTimeImmutable($at));
    }
}
