<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Domain;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptCharge;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptLine;
use Phil\ReceiptEmail\Domain\ValueObject\TaxAmount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Party::class)]
#[CoversClass(TaxAmount::class)]
#[CoversClass(ReceiptCharge::class)]
#[CoversClass(ReceiptLine::class)]
final class ValueObjectsTest extends TestCase
{
    public function testAPartyHasANameAndAnAddress(): void
    {
        $party = Party::of('The Shop', ['12 Main Street', 'Montréal']);

        self::assertSame('The Shop', $party->name);
        self::assertSame(['12 Main Street', 'Montréal'], $party->address);
        self::assertSame([], Party::of('The Shop')->address);
    }

    public function testAPartyHasAName(): void
    {
        $this->expectExceptionMessageIs('A name is a text of 1 to 500 characters, without line breaks or control characters.');

        Party::of(' ');
    }

    public function testAnAddressHasTenLinesAtMost(): void
    {
        $this->expectExceptionMessageIs('An address holds a list of at most 10 lines.');

        Party::of('The Shop', array_fill(0, 11, 'line'));
    }

    public function testATaxHasACodeAnAmountAndWhetherThePricesIncludedIt(): void
    {
        $added = TaxAmount::of('GST', $this->cad(75));
        $included = TaxAmount::of('QST', $this->cad(150), true);

        self::assertSame(['GST', 75, false], [$added->code, $added->amount->minorUnits, $added->included]);
        self::assertSame(['QST', 150, true], [$included->code, $included->amount->minorUnits, $included->included]);
        self::assertSame(str_repeat('T', 64), TaxAmount::of(str_repeat('T', 64), $this->cad(1))->code);
    }

    public function testATaxCodeIsShort(): void
    {
        $this->expectExceptionMessageIs('A tax code is a text of 1 to 64 characters, without line breaks or control characters.');

        TaxAmount::of(str_repeat('T', 65), $this->cad(1));
    }

    public function testAChargeHasALabel(): void
    {
        $charge = ReceiptCharge::of('Delivery', $this->cad(1_000));

        self::assertSame(['Delivery', 1_000], [$charge->label, $charge->amount->minorUnits]);

        $this->expectExceptionMessageIs('The label of a charge is a text of 1 to 500 characters, without line breaks or control characters.');

        ReceiptCharge::of('', $this->cad(1_000));
    }

    public function testALineKeepsWhatWasIssued(): void
    {
        $line = ReceiptLine::of('Teapot', 2, $this->cad(3_000), $this->cad(-6_000), [TaxAmount::of('GST', $this->cad(-300))], ['Colour: Blue'], ['Glaze']);

        self::assertSame('Teapot', $line->description);
        self::assertSame(2, $line->quantity);
        self::assertSame(3_000, $line->unitPrice->minorUnits);
        self::assertSame(-6_000, $line->amount->minorUnits);
        self::assertSame('GST', $line->taxes[0]->code);
        self::assertSame(['Colour: Blue'], $line->options);
        self::assertSame(['Glaze'], $line->pending);

        $plain = ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500));
        self::assertSame([[], [], []], [$plain->taxes, $plain->options, $plain->pending]);
    }

    public function testALineHoldsAtLeastOneUnit(): void
    {
        self::assertSame(1, ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500))->quantity);

        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs('A line holds at least one unit.');

        ReceiptLine::of('Tea', 0, $this->cad(500), $this->cad(0));
    }

    public function testALineHasADescription(): void
    {
        $this->expectExceptionMessageIs('The description of a line is a text of 1 to 500 characters, without line breaks or control characters.');

        ReceiptLine::of(' ', 1, $this->cad(500), $this->cad(500));
    }

    public function testTheAmountOfALineIsInItsCurrency(): void
    {
        $this->expectExceptionMessageIs('The amount of a line is in USD, and the receipt in CAD.');

        ReceiptLine::of('Tea', 1, $this->cad(500), Money::of(500, Currency::of('USD')));
    }

    public function testTheTaxesOfALineAreInItsCurrency(): void
    {
        $this->expectExceptionMessageIs('A tax of a line is in USD, and the receipt in CAD.');

        ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), [TaxAmount::of('GST', $this->cad(25)), TaxAmount::of('X', Money::of(1, Currency::of('USD')))]);
    }

    public function testALineHoldsTwentyTaxesAtMost(): void
    {
        $taxes = array_fill(0, 20, TaxAmount::of('GST', $this->cad(1)));
        self::assertCount(20, ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), $taxes)->taxes);

        $this->expectExceptionMessageIs('A line holds a list of at most 20 taxes.');

        ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), [...$taxes, TaxAmount::of('GST', $this->cad(1))]);
    }

    public function testTheOptionsOfALineAreChecked(): void
    {
        self::assertCount(20, ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), [], array_fill(0, 20, 'a'), array_fill(0, 20, 'b'))->options);

        try {
            ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), [], array_fill(0, 21, 'a'));
            self::fail('Twenty-one options are refused.');
        } catch (InvalidReceipt $invalidReceipt) {
            self::assertSame('The options of a line holds a list of at most 20 lines.', $invalidReceipt->getMessage());
        }

        $this->expectExceptionMessageIs('The pending options of a line holds a list of at most 20 lines.');

        ReceiptLine::of('Tea', 1, $this->cad(500), $this->cad(500), [], [], array_fill(0, 21, 'b'));
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
