<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Party;

/**
 * Gifts and their official receipts.
 */
final class Donations
{
    public static function charity(): Party
    {
        return Party::of('The Reading Circle', ['8 Library Lane', 'Montréal QC  H2X 2Z9']);
    }

    public static function donor(): Party
    {
        return Party::of('Ada B. Lovelace', ['1 Analytical Road', 'Québec QC  G1R 4P5']);
    }

    /**
     * A gift of 150.00 for a gala dinner ticket worth 40.00.
     */
    public static function cash(int $amount = 15_000, int $advantage = 4_000, DateTimeImmutable|int|null $received = null): Donation
    {
        $cad = Currency::of('CAD');

        return Donation::of(
            self::charity(),
            '123456789 RR 0001',
            'Montréal',
            self::donor(),
            $received ?? new DateTimeImmutable('2026-11-18'),
            GiftKind::Cash,
            Money::of($amount, $cad),
            Money::of($advantage, $cad),
            $advantage > 0 ? 'A ticket to the gala dinner' : null,
            null,
            null,
            'Grace Hopper, treasurer',
        );
    }

    public static function property(): Donation
    {
        $cad = Currency::of('CAD');

        return Donation::of(
            self::charity(),
            '123456789 RR 0001',
            'Montréal',
            self::donor(),
            2026,
            GiftKind::NonCash,
            Money::of(120_000, $cad),
            null,
            null,
            'A first edition of a book on the analytical engine',
            Party::of('Charles Babbage, appraiser', ['2 Difference Street', 'Laval QC  H7N 1A1']),
            'Grace Hopper, treasurer',
        );
    }

    public static function receipt(?Donation $donation = null): DonationReceipt
    {
        return DonationReceipt::of('D-2026-000017', new DateTimeImmutable('2026-11-20 10:00:00'), $donation ?? self::cash());
    }
}
