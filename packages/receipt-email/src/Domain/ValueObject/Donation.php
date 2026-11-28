<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use DateTimeImmutable;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\Guard;

/**
 * A gift to a registered charity, as an official donation receipt states it: the charity, its registration number and
 * where the receipt is issued, the donor, the date or the year the gift was received, its amount, the advantage the
 * donor received and its description, and who signs for the charity. A gift of property also has its description,
 * and the appraiser who valued it when it was appraised.
 *
 * The eligible amount is the amount less the advantage, and is never zero or below: a gift whose advantage is worth as
 * much is refused. Whether a gift qualifies for a receipt at all is the charity's decision, not the package's.
 */
final readonly class Donation
{
    private function __construct(
        public Party $charity,
        public string $registrationNumber,
        public string $issuedIn,
        public Party $donor,
        public DateTimeImmutable|int $received,
        public GiftKind $kind,
        public Money $amount,
        public Money $advantage,
        public ?string $advantageDescription,
        public ?string $giftDescription,
        public ?Party $appraiser,
        public string $signatory,
    ) {
    }

    /**
     * @param Party                 $charity              its name and address as on file with the tax authority
     * @param string                $registrationNumber   the charity's registration number, as `123456789 RR 0001`
     * @param string                $issuedIn             the city, town or municipality where the receipt is issued
     * @param Party                 $donor                the donor's full name, with their middle initial, and address
     * @param DateTimeImmutable|int $received             the date the gift was received, or its year alone
     * @param Money                 $amount               the gift, at fair market value for property
     * @param Money|null            $advantage            what the donor received in return, zero when nothing
     * @param string|null           $advantageDescription required when there is an advantage
     * @param string|null           $giftDescription      required for property
     * @param Party|null            $appraiser            for appraised property only
     * @param string                $signatory            the individual the charity authorises to acknowledge gifts
     */
    public static function of(
        Party $charity,
        string $registrationNumber,
        string $issuedIn,
        Party $donor,
        DateTimeImmutable|int $received,
        GiftKind $kind,
        Money $amount,
        ?Money $advantage,
        ?string $advantageDescription,
        ?string $giftDescription,
        ?Party $appraiser,
        string $signatory,
    ): self {
        Guard::text($registrationNumber, 'The registration number of the charity', Guard::MAX_IDENTIFIER);
        Guard::text($issuedIn, 'Where the receipt is issued');
        Guard::text($signatory, 'The signatory');

        if (\is_int($received) && ($received < 1900 || $received > 9999)) {
            throw InvalidReceipt::because('The year a gift was received is written with four digits, from 1900.');
        }

        if (!$amount->isPositive()) {
            throw InvalidReceipt::because('The amount of a gift is above zero.');
        }

        $advantage = Guard::currency($advantage ?? Money::zero($amount->currency), $amount->currency, 'The advantage');

        if ($advantage->isNegative() || !$amount->isGreaterThan($advantage)) {
            throw InvalidReceipt::because('The advantage is zero or more, and less than the amount of the gift: the eligible amount is above zero.');
        }

        if (null !== $advantageDescription) {
            Guard::text($advantageDescription, 'The description of the advantage');
        }

        if ($advantage->isPositive() && null === $advantageDescription) {
            throw InvalidReceipt::because('An advantage is described on the receipt.');
        }

        if (null !== $giftDescription) {
            Guard::text($giftDescription, 'The description of the gift');
        }

        if (GiftKind::NonCash === $kind && null === $giftDescription) {
            throw InvalidReceipt::because('A gift of property is described on the receipt.');
        }

        if (GiftKind::Cash === $kind && $appraiser instanceof Party) {
            throw InvalidReceipt::because('A gift of cash has no appraiser.');
        }

        return new self($charity, $registrationNumber, $issuedIn, $donor, $received, $kind, $amount, $advantage, $advantageDescription, $giftDescription, $appraiser, $signatory);
    }

    /**
     * The amount of the gift less the advantage: what the receipt states as eligible.
     */
    public function eligibleAmount(): Money
    {
        return $this->amount->subtract($this->advantage);
    }
}
