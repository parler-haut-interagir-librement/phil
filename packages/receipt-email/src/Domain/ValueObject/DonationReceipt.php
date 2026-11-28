<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ReceiptEmail\Domain\Guard;

/**
 * An official donation receipt: a gift, the serial number of the series of donation receipts — never one of the
 * payment receipts —, and the date it was issued. It names the tax authority and its website, as an official receipt
 * must.
 */
final readonly class DonationReceipt
{
    /** The tax authority an official receipt names, in English. */
    public const string AUTHORITY_EN = 'Canada Revenue Agency';

    /** The tax authority an official receipt names, in French. */
    public const string AUTHORITY_FR = 'Agence du revenu du Canada';

    /** The tax authority's website an official receipt gives. */
    public const string AUTHORITY_WEBSITE = 'canada.ca/charities-giving';

    private function __construct(
        public string $serialNumber,
        public DateTimeImmutable $issuedAt,
        public Donation $donation,
    ) {
    }

    /**
     * @param string $serialNumber unique in the series of donation receipts, 1 to 32 visible ASCII characters
     */
    public static function of(string $serialNumber, DateTimeImmutable $issuedAt, Donation $donation): self
    {
        return new self(Guard::identifier($serialNumber, 'The serial number of a donation receipt', 32), $issuedAt, $donation);
    }

    /**
     * The tax authority's name in the language of the locale: French for `fr` and its regions, English otherwise.
     */
    public static function authority(string $locale): string
    {
        return 1 === preg_match('/^fr(?:[_-]|$)/i', $locale) ? self::AUTHORITY_FR : self::AUTHORITY_EN;
    }
}
