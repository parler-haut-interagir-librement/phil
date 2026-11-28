<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\Payment\PaymentDocument;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptCharge;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptLine;
use Phil\ReceiptEmail\Domain\ValueObject\TaxAmount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentReceipt::class)]
final class PaymentReceiptTest extends TestCase
{
    public function testAReceiptKeepsTheAmountsItWasIssuedWith(): void
    {
        $receipt = $this->receipt();

        self::assertSame(PaymentDocument::Receipt, $receipt->document);
        self::assertSame('R-1', $receipt->number);
        self::assertSame('A-1', $receipt->orderNumber);
        self::assertNull($receipt->creditedReceipt);
        self::assertSame('2026-11-20', $receipt->issuedAt->format('Y-m-d'));
        self::assertSame('The Shop', $receipt->issuer->name);
        self::assertSame(['GST' => '123456789 RT0001'], $receipt->taxNumbers);
        self::assertSame('CAD', $receipt->currency->code);
        self::assertCount(1, $receipt->lines);
        self::assertSame('Delivery', $receipt->charges[0]->label);
        // The total is the issuer's, even when it is not the sum of the parts: it is never computed again.
        self::assertSame(1_234, $receipt->total->minorUnits);
        self::assertSame(1_500, $receipt->itemsTotal->minorUnits);
    }

    public function testTheTaxesAddedAndIncludedAreTold(): void
    {
        $receipt = $this->receipt(taxes: [TaxAmount::of('GST', $this->cad(75)), TaxAmount::of('QST', $this->cad(150), true), TaxAmount::of('HST', $this->cad(10), true)]);

        self::assertSame(['GST'], array_map(static fn (TaxAmount $tax): string => $tax->code, $receipt->addedTaxes()));
        self::assertSame(['QST', 'HST'], array_map(static fn (TaxAmount $tax): string => $tax->code, $receipt->includedTaxes()));
        self::assertSame(160, $receipt->includedTotal()?->minorUnits);
        self::assertNull($this->receipt(taxes: [TaxAmount::of('GST', $this->cad(75))])->includedTotal());
        self::assertSame([], $this->receipt(taxes: [TaxAmount::of('QST', $this->cad(1), true)])->addedTaxes());
        // Each list is numbered from 0, whatever stands before it.
        self::assertSame([0], array_keys($this->receipt(taxes: [TaxAmount::of('QST', $this->cad(1), true), TaxAmount::of('GST', $this->cad(5))])->addedTaxes()));
    }

    public function testACreditNoteNamesTheReceiptItCredits(): void
    {
        $note = $this->receipt(PaymentDocument::CreditNote, 'R-1');

        self::assertSame('R-1', $note->creditedReceipt);

        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs('A credit note names the receipt it credits, and a receipt names none.');

        $this->receipt(PaymentDocument::CreditNote);
    }

    public function testAReceiptNamesNoCreditedReceipt(): void
    {
        $this->expectExceptionMessageIs('A credit note names the receipt it credits, and a receipt names none.');

        $this->receipt(PaymentDocument::Receipt, 'R-0');
    }

    public function testTheCreditedReceiptIsANumber(): void
    {
        $this->expectExceptionMessageIs('The number of the credited receipt is 1 to 64 visible ASCII characters.');

        $this->receipt(PaymentDocument::CreditNote, 'R 1');
    }

    public function testTheNumbersAreChecked(): void
    {
        try {
            $this->receipt(number: 'R 1');
            self::fail('A number with a space is refused.');
        } catch (InvalidReceipt $invalidReceipt) {
            self::assertSame('The number of a receipt is 1 to 64 visible ASCII characters.', $invalidReceipt->getMessage());
        }

        $this->expectExceptionMessageIs('The number of an order is 1 to 64 visible ASCII characters.');

        $this->receipt(order: '');
    }

    public function testTheTaxNumbersAreTexts(): void
    {
        try {
            $this->receipt(taxNumbers: ['' => '1']);
            self::fail('An empty code is refused.');
        } catch (InvalidReceipt $invalidReceipt) {
            self::assertStringStartsWith('A tax code is a text of 1 to 64', $invalidReceipt->getMessage());
        }

        $this->expectExceptionMessageIs('A tax registration number is a text of 1 to 64 characters, without line breaks or control characters.');

        $this->receipt(taxNumbers: ['GST' => str_repeat('1', 65)]);
    }

    public function testAReceiptHasLines(): void
    {
        $this->expectExceptionMessageIs('A receipt holds 1 to 500 lines, and at most as many charges and taxes.');

        $this->receipt(lines: []);
    }

    public function testAReceiptHoldsFiveHundredLinesChargesAndTaxesAtMost(): void
    {
        $line = $this->line();
        $charge = ReceiptCharge::of('Fee', $this->cad(1));
        $tax = TaxAmount::of('GST', $this->cad(1));
        $full = $this->receipt(lines: array_fill(0, 500, $line), charges: array_fill(0, 500, $charge), taxes: array_fill(0, 500, $tax));
        self::assertCount(500, $full->lines);

        $cases = [
            fn (): PaymentReceipt => $this->receipt(lines: array_fill(0, 501, $line)),
            fn (): PaymentReceipt => $this->receipt(charges: array_fill(0, 501, $charge)),
            fn (): PaymentReceipt => $this->receipt(taxes: array_fill(0, 501, $tax)),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A list of 501 is refused.');
            } catch (InvalidReceipt $exception) {
                self::assertSame('A receipt holds 1 to 500 lines, and at most as many charges and taxes.', $exception->getMessage());
            }
        }
    }

    public function testEveryAmountIsInTheCurrencyOfTheTotal(): void
    {
        $usd = Money::of(1, Currency::of('USD'));
        $cases = [
            'The subtotal' => fn (): PaymentReceipt => $this->receipt(itemsTotal: $usd),
            'A line' => fn (): PaymentReceipt => $this->receipt(lines: [$this->line(), ReceiptLine::of('Tea', 1, $usd, $usd)]),
            'A charge' => fn (): PaymentReceipt => $this->receipt(charges: [ReceiptCharge::of('Fee', $this->cad(1)), ReceiptCharge::of('Fee', $usd)]),
            'A tax' => fn (): PaymentReceipt => $this->receipt(taxes: [TaxAmount::of('GST', $this->cad(1)), TaxAmount::of('GST', $usd)]),
        ];

        foreach ($cases as $field => $case) {
            try {
                $case();
                self::fail($field.' in another currency is refused.');
            } catch (InvalidReceipt $exception) {
                self::assertSame($field.' is in USD, and the receipt in CAD.', $exception->getMessage());
            }
        }
    }

    /**
     * @param array<string, string>|null $taxNumbers
     * @param list<ReceiptLine>|null     $lines
     * @param list<ReceiptCharge>|null   $charges
     * @param list<TaxAmount>|null       $taxes
     */
    private function receipt(
        PaymentDocument $document = PaymentDocument::Receipt,
        ?string $credited = null,
        string $number = 'R-1',
        string $order = 'A-1',
        ?array $taxNumbers = null,
        ?array $lines = null,
        ?array $charges = null,
        ?array $taxes = null,
        ?Money $itemsTotal = null,
    ): PaymentReceipt {
        return PaymentReceipt::of(
            $document,
            $number,
            $order,
            $credited,
            new DateTimeImmutable('2026-11-20'),
            Party::of('The Shop'),
            $taxNumbers ?? ['GST' => '123456789 RT0001'],
            $lines ?? [$this->line()],
            $charges ?? [ReceiptCharge::of('Delivery', $this->cad(1_000))],
            $taxes ?? [],
            $itemsTotal ?? $this->cad(1_500),
            $this->cad(1_234),
        );
    }

    private function line(): ReceiptLine
    {
        return ReceiptLine::of('Tea', 3, $this->cad(500), $this->cad(1_500));
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
