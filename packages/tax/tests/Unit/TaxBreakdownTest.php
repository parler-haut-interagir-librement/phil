<?php

declare(strict_types=1);

namespace Phil\Tax\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Tax\Exception\InvalidBreakdown;
use Phil\Tax\Exception\TaxException;
use Phil\Tax\TaxBreakdown;
use Phil\Tax\TaxLine;
use Phil\Tax\TaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxBreakdown::class)]
#[CoversClass(TaxLine::class)]
#[CoversClass(InvalidBreakdown::class)]
final class TaxBreakdownTest extends TestCase
{
    public function testTotalsAddEveryLineToTheBase(): void
    {
        $base = Money::of(1_000, Currency::of('CAD'));
        $breakdown = TaxBreakdown::of($base, [
            TaxLine::computed(TaxRate::of('GST', 50_000), $base),
            TaxLine::computed(TaxRate::of('QST', 99_750), $base),
        ]);

        self::assertSame(150, $breakdown->totalTax()->minorUnits);
        self::assertSame(1_150, $breakdown->totalWithTax()->minorUnits);
        self::assertSame('CAD', $breakdown->totalTax()->currency->code);
    }

    public function testAnEmptyBreakdownHasNoTax(): void
    {
        $breakdown = TaxBreakdown::of(Money::of(1_000, Currency::of('EUR')), []);

        self::assertTrue($breakdown->totalTax()->isZero());
        self::assertSame('EUR', $breakdown->totalTax()->currency->code);
        self::assertSame(1_000, $breakdown->totalWithTax()->minorUnits);
    }

    public function testALineIsFoundByItsCode(): void
    {
        $base = Money::of(1_999, Currency::of('CAD'));
        $breakdown = TaxBreakdown::of($base, [
            TaxLine::computed(TaxRate::of('GST', 50_000), $base),
            TaxLine::computed(TaxRate::of('QST', 99_750), $base),
        ]);

        self::assertSame(199, $breakdown->line('QST')?->amount->minorUnits);
        self::assertSame(100, $breakdown->line('GST')?->amount->minorUnits);
        self::assertNull($breakdown->line('HST'));
    }

    /**
     * A credit note reverses each itemised tax, line by line: the reversed totals are the exact negation.
     */
    public function testTheReversalNegatesEveryLine(): void
    {
        $base = Money::of(1_999, Currency::of('CAD'));
        $breakdown = TaxBreakdown::of($base, [
            TaxLine::computed(TaxRate::of('GST', 50_000), $base),
            TaxLine::computed(TaxRate::of('QST', 99_750), $base),
        ]);
        $reversed = $breakdown->reversed();

        self::assertSame(-1_999, $reversed->base->minorUnits);
        self::assertSame(['GST', 'QST'], array_map(static fn (TaxLine $line): string => $line->rate->code, $reversed->lines));
        self::assertSame([-1_999, -1_999], array_map(static fn (TaxLine $line): int => $line->base->minorUnits, $reversed->lines));
        self::assertSame([-100, -199], array_map(static fn (TaxLine $line): int => $line->amount->minorUnits, $reversed->lines));
        self::assertTrue($reversed->totalWithTax()->equals($breakdown->totalWithTax()->negate()));
        self::assertTrue($reversed->reversed()->totalWithTax()->equals($breakdown->totalWithTax()));
    }

    public function testAReportedAmountIsKeptAsGiven(): void
    {
        $base = Money::of(1_000, Currency::of('CAD'));
        $line = TaxLine::reported(TaxRate::of('HST', 130_000), $base, Money::of(131, Currency::of('CAD')));

        self::assertSame(131, TaxBreakdown::of($base, [$line])->totalTax()->minorUnits);
        self::assertSame(-131, $line->reversed()->amount->minorUnits);
    }

    public function testAReportedAmountInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);
        $this->expectExceptionMessageIsOrContains('An amount in CAD cannot be combined with an amount in EUR.');

        TaxLine::reported(TaxRate::of('VAT', 200_000), Money::of(1_000, Currency::of('CAD')), Money::of(200, Currency::of('EUR')));
    }

    public function testALineOnAnotherBaseIsRefused(): void
    {
        $cad = Currency::of('CAD');

        $this->expectException(InvalidBreakdown::class);
        $this->expectExceptionMessageIsOrContains('The QST line is on another base: every rate of a breakdown applies to the same base.');

        TaxBreakdown::of(Money::of(1_000, $cad), [
            TaxLine::computed(TaxRate::of('GST', 50_000), Money::of(1_000, $cad)),
            TaxLine::computed(TaxRate::of('QST', 99_750), Money::of(1_050, $cad)),
        ]);
    }

    public function testALineInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);
        $this->expectExceptionMessageIsOrContains('An amount in CAD cannot be combined with an amount in EUR.');

        TaxBreakdown::of(Money::of(1_000, Currency::of('CAD')), [
            TaxLine::computed(TaxRate::of('VAT', 200_000), Money::of(1_000, Currency::of('EUR'))),
        ]);
    }

    public function testARateCodeAppearsOnlyOnce(): void
    {
        $base = Money::of(1_000, Currency::of('CAD'));

        $this->expectException(InvalidBreakdown::class);
        $this->expectExceptionMessageIsOrContains('The GST rate appears twice in one breakdown.');

        TaxBreakdown::of($base, [
            TaxLine::computed(TaxRate::of('GST', 50_000), $base),
            TaxLine::computed(TaxRate::of('QST', 99_750), $base),
            TaxLine::computed(TaxRate::of('GST', 60_000), $base),
        ]);
    }

    public function testABreakdownErrorIsATaxException(): void
    {
        self::assertInstanceOf(TaxException::class, InvalidBreakdown::duplicateRate('GST'));
    }
}
