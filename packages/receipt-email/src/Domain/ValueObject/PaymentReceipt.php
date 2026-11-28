<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\Guard;
use Phil\ReceiptEmail\Domain\Payment\PaymentDocument;

/**
 * A payment receipt or a credit note as its issuer numbered it, read for an email: every amount is the issuer's, as
 * it was issued, never computed again here.
 *
 * Its taxes are listed twice: on each line, and summed by rate code over the receipt. A tax the prices included is
 * shown, never added to the total.
 */
final readonly class PaymentReceipt
{
    /** The most lines, charges or taxes a receipt holds. */
    public const int MAX_ITEMS = 500;

    /**
     * @param array<string, string> $taxNumbers
     * @param list<ReceiptLine>     $lines
     * @param list<ReceiptCharge>   $charges
     * @param list<TaxAmount>       $taxes
     */
    private function __construct(
        public PaymentDocument $document,
        public string $number,
        public string $orderNumber,
        public ?string $creditedReceipt,
        public DateTimeImmutable $issuedAt,
        public Party $issuer,
        public array $taxNumbers,
        public Currency $currency,
        public array $lines,
        public array $charges,
        public array $taxes,
        public Money $itemsTotal,
        public Money $total,
    ) {
    }

    /**
     * @param array<string, string> $taxNumbers      rate code → the issuer's registration number for it
     * @param list<ReceiptLine>     $lines           at least one
     * @param list<ReceiptCharge>   $charges         the amounts other than lines and taxes
     * @param list<TaxAmount>       $taxes           each tax summed over the receipt, by rate code
     * @param string|null           $creditedReceipt the receipt a credit note reverses
     */
    public static function of(
        PaymentDocument $document,
        string $number,
        string $orderNumber,
        ?string $creditedReceipt,
        DateTimeImmutable $issuedAt,
        Party $issuer,
        array $taxNumbers,
        array $lines,
        array $charges,
        array $taxes,
        Money $itemsTotal,
        Money $total,
    ): self {
        Guard::identifier($number, 'The number of a receipt');
        Guard::identifier($orderNumber, 'The number of an order');

        if ((PaymentDocument::CreditNote === $document) !== (null !== $creditedReceipt)) {
            throw InvalidReceipt::because('A credit note names the receipt it credits, and a receipt names none.');
        }

        if (null !== $creditedReceipt) {
            Guard::identifier($creditedReceipt, 'The number of the credited receipt');
        }

        foreach ($taxNumbers as $code => $registration) {
            Guard::text($code, 'A tax code', Guard::MAX_IDENTIFIER);
            Guard::text($registration, 'A tax registration number', Guard::MAX_IDENTIFIER);
        }

        if ([] === $lines || \count($lines) > self::MAX_ITEMS || \count($charges) > self::MAX_ITEMS || \count($taxes) > self::MAX_ITEMS) {
            throw InvalidReceipt::because(\sprintf('A receipt holds 1 to %d lines, and at most as many charges and taxes.', self::MAX_ITEMS));
        }

        $currency = $total->currency;
        Guard::currency($itemsTotal, $currency, 'The subtotal');

        foreach ($lines as $line) {
            Guard::currency($line->amount, $currency, 'A line');
        }

        foreach ($charges as $charge) {
            Guard::currency($charge->amount, $currency, 'A charge');
        }

        foreach ($taxes as $tax) {
            Guard::currency($tax->amount, $currency, 'A tax');
        }

        return new self($document, $number, $orderNumber, $creditedReceipt, $issuedAt, $issuer, $taxNumbers, $currency, $lines, $charges, $taxes, $itemsTotal, $total);
    }

    /**
     * @return list<TaxAmount> the taxes added to the total
     */
    public function addedTaxes(): array
    {
        return array_values(array_filter($this->taxes, static fn (TaxAmount $tax): bool => !$tax->included));
    }

    /**
     * @return list<TaxAmount> the taxes the prices included
     */
    public function includedTaxes(): array
    {
        return array_values(array_filter($this->taxes, static fn (TaxAmount $tax): bool => $tax->included));
    }

    /**
     * What the buyer paid in taxes already in the prices, or `null` when the taxes were added.
     */
    public function includedTotal(): ?Money
    {
        $included = $this->includedTaxes();

        return [] === $included ? null : Money::sum(...array_map(static fn (TaxAmount $tax): Money => $tax->amount, $included));
    }
}
