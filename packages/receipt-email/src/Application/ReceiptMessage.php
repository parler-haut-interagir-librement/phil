<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

use DateTimeImmutable;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptCharge;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptLine;
use Phil\ReceiptEmail\Domain\ValueObject\TaxAmount;

/**
 * What a receipt's email says, before it is rendered: the template it is written with, the locale, and its variables.
 *
 * The variables are the receipt's own values, copied as issued: every amount is the issuer's, written as a decimal
 * of the receipt's currency (`-12.50` on a credit note), never computed again. A line's taxes and options are lists of
 * texts, `GST 0.63` and `Size: M`, so that a template iterates them without nesting objects. They hold nothing but the
 * receipt and the recipient's name: no offer, no tracking identifier.
 *
 * @phpstan-type Amount string
 * @phpstan-type PaymentVariables array{
 *     recipient: array{name: ?string},
 *     receipt: array{number: string, document: string, order: string, credits: ?string, issued_at: DateTimeImmutable, currency: string, subtotal: Amount, total: Amount, included_total: ?Amount},
 *     issuer: array{name: string, address: list<string>, tax_numbers: list<string>},
 *     lines: list<array{description: string, quantity: int, unit_price: Amount, amount: Amount, taxes: list<string>, options: list<string>, pending: list<string>}>,
 *     charges: list<array{label: string, amount: Amount}>,
 *     taxes: list<array{code: string, amount: Amount}>,
 *     included_taxes: list<array{code: string, amount: Amount}>,
 * }
 * @phpstan-type DonationVariables array{
 *     recipient: array{name: ?string},
 *     receipt: array{serial_number: string, issued_at: DateTimeImmutable, issued_in: string, received_on: ?DateTimeImmutable, received_year: int, kind: string, currency: string, amount: Amount, advantage: Amount, advantage_description: ?string, eligible_amount: Amount, gift_description: ?string, signatory: string},
 *     charity: array{name: string, address: list<string>, registration_number: string},
 *     donor: array{name: string, address: list<string>},
 *     appraiser: ?array{name: string, address: list<string>},
 *     authority: array{name: string, website: string},
 * }
 */
final readonly class ReceiptMessage
{
    /** The template of a payment receipt or a credit note. */
    public const string PAYMENT = 'receipt';

    /** The template of an official donation receipt. */
    public const string DONATION = 'donation-receipt';

    /**
     * @param array<string, mixed> $variables
     */
    private function __construct(
        public string $template,
        public string $locale,
        public array $variables,
    ) {
    }

    public static function payment(PaymentReceipt $receipt, Recipient $recipient, string $template = self::PAYMENT): self
    {
        return new self($template, $recipient->locale, self::paymentVariables($receipt, $recipient));
    }

    public static function donation(DonationReceipt $receipt, Recipient $recipient, string $template = self::DONATION): self
    {
        return new self($template, $recipient->locale, self::donationVariables($receipt, $recipient));
    }

    /**
     * @return PaymentVariables
     */
    public static function paymentVariables(PaymentReceipt $receipt, Recipient $recipient): array
    {
        return [
            'recipient' => ['name' => $recipient->name()],
            'receipt' => [
                'number' => $receipt->number,
                'document' => $receipt->document->value,
                'order' => $receipt->orderNumber,
                'credits' => $receipt->creditedReceipt,
                'issued_at' => $receipt->issuedAt,
                'currency' => $receipt->currency->code,
                'subtotal' => $receipt->itemsTotal->toDecimal(),
                'total' => $receipt->total->toDecimal(),
                'included_total' => $receipt->includedTotal()?->toDecimal(),
            ],
            'issuer' => [
                'name' => $receipt->issuer->name,
                'address' => $receipt->issuer->address,
                'tax_numbers' => array_map(static fn (string $code, string $number): string => $code.' '.$number, array_keys($receipt->taxNumbers), array_values($receipt->taxNumbers)),
            ],
            'lines' => array_map(static fn (ReceiptLine $line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice->toDecimal(),
                'amount' => $line->amount->toDecimal(),
                'taxes' => array_map(self::taxText(...), $line->taxes),
                'options' => $line->options,
                'pending' => $line->pending,
            ], $receipt->lines),
            'charges' => array_map(static fn (ReceiptCharge $charge): array => ['label' => $charge->label, 'amount' => $charge->amount->toDecimal()], $receipt->charges),
            'taxes' => array_map(self::tax(...), $receipt->addedTaxes()),
            'included_taxes' => array_map(self::tax(...), $receipt->includedTaxes()),
        ];
    }

    /**
     * @return DonationVariables
     */
    public static function donationVariables(DonationReceipt $receipt, Recipient $recipient): array
    {
        $donation = $receipt->donation;

        return [
            'recipient' => ['name' => $recipient->name()],
            'receipt' => [
                'serial_number' => $receipt->serialNumber,
                'issued_at' => $receipt->issuedAt,
                'issued_in' => $donation->issuedIn,
                'received_on' => $donation->received instanceof DateTimeImmutable ? $donation->received : null,
                'received_year' => $donation->received instanceof DateTimeImmutable ? (int) $donation->received->format('Y') : $donation->received,
                'kind' => $donation->kind->value,
                'currency' => $donation->amount->currency->code,
                'amount' => $donation->amount->toDecimal(),
                'advantage' => $donation->advantage->toDecimal(),
                'advantage_description' => $donation->advantageDescription,
                'eligible_amount' => $donation->eligibleAmount()->toDecimal(),
                'gift_description' => $donation->giftDescription,
                'signatory' => $donation->signatory,
            ],
            'charity' => [...self::party($donation->charity), 'registration_number' => $donation->registrationNumber],
            'donor' => self::party($donation->donor),
            'appraiser' => $donation->appraiser instanceof Party ? self::party($donation->appraiser) : null,
            'authority' => ['name' => DonationReceipt::authority($recipient->locale), 'website' => DonationReceipt::AUTHORITY_WEBSITE],
        ];
    }

    /**
     * @return array{code: string, amount: string}
     */
    private static function tax(TaxAmount $tax): array
    {
        return ['code' => $tax->code, 'amount' => $tax->amount->toDecimal()];
    }

    private static function taxText(TaxAmount $tax): string
    {
        return $tax->code.' '.$tax->amount->toDecimal();
    }

    /**
     * @return array{name: string, address: list<string>}
     */
    private static function party(Party $party): array
    {
        return ['name' => $party->name, 'address' => $party->address];
    }
}
