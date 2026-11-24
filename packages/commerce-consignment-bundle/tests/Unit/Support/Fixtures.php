<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The values the tests share: dollars, instants, and the receipt of five copies of a book bought and five consigned.
 */
final class Fixtures
{
    public const string BOOK = 'BOOK-1';

    public const string AUTHOR = 'author-ann';

    public const string SHELF = 'store';

    public const string BACK = 'back-room';

    public static function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    /**
     * An instant on 2026-11-20, in Montréal's time zone so that the ledger has to bring it back to UTC.
     */
    public static function at(int $hour, int $minute = 0): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('2026-11-20 %02d:%02d:00', $hour, $minute), new DateTimeZone('America/Toronto'));
    }

    /**
     * An instant in UTC, written `2026-11-20 15:00:00`.
     */
    public static function utc(string $instant): DateTimeImmutable
    {
        return new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }

    /**
     * Five copies bought at 12.00 and five left by the author for a share of 15.00 each.
     */
    public static function mixedReceipt(string $reference = 'DN-1', string $location = self::SHELF): Receipt
    {
        return Receipt::of($reference, $location, [
            ReceiptLine::purchased(self::BOOK, 5, self::cad(1200)),
            ReceiptLine::consigned(self::BOOK, 5, self::AUTHOR, ConsignmentTerms::share(self::cad(1500))),
        ]);
    }

    public static function purchased(int $quantity, int $cost, string $reference = 'DN-P', string $location = self::SHELF): Receipt
    {
        return Receipt::of($reference, $location, [ReceiptLine::purchased(self::BOOK, $quantity, self::cad($cost))]);
    }

    public static function consigned(int $quantity, string $consignor, ConsignmentTerms $terms, string $reference = 'CS-1', string $location = self::SHELF): Receipt
    {
        return Receipt::of($reference, $location, [ReceiptLine::consigned(self::BOOK, $quantity, $consignor, $terms)]);
    }
}
