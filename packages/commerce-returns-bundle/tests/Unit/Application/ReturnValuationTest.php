<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\ReturnValuation;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Specials;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnValuation::class)]
#[CoversClass(DeliveryRefund::class)]
final class ReturnValuationTest extends TestCase
{
    private const string SPECIAL = 'promotion:duo';

    /**
     * A book at 30.00 and dice at 20.00, 40.00 together: the special's 10.00 off is spread, 6.00 and 4.00; a 10 %
     * off the book apart from it, 3.00, which no return takes back. No tax.
     */
    public function testReturningOneProductOfASpecialTakesItsWholeDiscountBack(): void
    {
        $order = $this->special(1, 1);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        self::assertSame([3_000 - 300], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
        self::assertSame([self::SPECIAL => 1_000], $valued['specialCharges']);
        self::assertSame(1_700, $this->value($order, $valued));

        $dice = $this->valuation()->value($order, [$this->ask('product:DICE', 1)], [$this->held($order, $valued)], DeliveryRefund::Never);
        self::assertSame([], $dice['specialCharges']);
        self::assertSame(2_000, $this->value($order, $dice));
    }

    public function testReturningTheWholeSpecialGivesBackWhatWasPaid(): void
    {
        $order = $this->special(1, 1);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1), $this->ask('product:DICE', 1)], [], DeliveryRefund::Never);

        self::assertSame(2_700 + 2_000 - 1_000, $this->value($order, $valued));
    }

    /**
     * Two specials bought, a book returned: one special breaks, half the discount; the second book breaks the other.
     */
    public function testEachSpecialBrokenTakesItsShare(): void
    {
        $order = $this->special(2, 2);
        $first = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);
        self::assertSame([self::SPECIAL => 1_000], $first['specialCharges']);

        $dice = $this->valuation()->value($order, [$this->ask('product:DICE', 1)], [$this->held($order, $first)], DeliveryRefund::Never);
        self::assertSame([], $dice['specialCharges'], 'The dice of the special already broken take nothing more.');

        $second = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [$this->held($order, $first)], DeliveryRefund::Never);
        self::assertSame([self::SPECIAL => 1_000], $second['specialCharges']);
    }

    /**
     * The special's discount lowered the book's tax: the tax it saved comes back with it.
     */
    public function testTheTaxASpecialSavedComesBackWithIt(): void
    {
        $order = $this->special(1, 1, taxed: true);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        // Book: 30.00 − 3.00 − 6.00 = 21.00, taxed 15 %: 3.15; the special saved 6.00 and its 0.90 of tax.
        self::assertSame([2_100 + 315 + 600 + 90], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
        // The whole special: 10.00, and the 0.90 and 0.60 of tax it saved on the book and the dice.
        self::assertSame([self::SPECIAL => 1_000 + 90 + 60], $valued['specialCharges']);
    }

    public function testWithoutASpecialsAdapterEveryDiscountStaysOnItsLine(): void
    {
        $order = $this->special(1, 1);
        $valued = new ReturnValuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        self::assertSame([2_100], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
        self::assertSame([], $valued['specialCharges']);
    }

    public function testTheChargeNeverExceedsWhatTheLinesAreWorth(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-2', $cad);
        $book = SellableReference::of('product', 'BOOK');
        $pin = SellableReference::of('product', 'PIN');
        $order->add(Sellable::of($book, 'Book', Money::of(3_000, $cad), 'standard'), 1, Money::of(3_000, $cad));
        $order->add(Sellable::of($pin, 'Pin', Money::of(200, $cad), 'standard'), 1, Money::of(200, $cad));
        $order->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-900, $cad), $book, origin: self::SPECIAL),
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-100, $cad), $pin, origin: self::SPECIAL),
        ]);
        $order->place(new DateTimeImmutable());

        $valued = $this->valuation()->value($order, [$this->ask('product:PIN', 1)], [], DeliveryRefund::Never);

        self::assertSame([self::SPECIAL => 200], $valued['specialCharges']);
        self::assertSame(0, $this->value($order, $valued));
    }

    public function testASetComesBackWhole(): void
    {
        $order = $this->special(1, 1);
        $valuation = new ReturnValuation(new Specials(sets: ['product:BOOK' => ['product:RULES', 'product:MAP']]));

        try {
            $valuation->value($order, [$this->ask('product:MAP', 1)], [], DeliveryRefund::Never);
            self::fail('A part of a set came back alone.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('In the order A-1, product:MAP came in the set product:BOOK: the set comes back whole, never a part of it.', $returnRefused->getMessage());
        }

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('The order A-1 has no line product:CUP.');

        $valuation->value($order, [$this->ask('product:CUP', 1)], [], DeliveryRefund::Never);
    }

    /**
     * @return iterable<string, array{DeliveryRefund, list<array{string, int, ReturnReason}>, int}>
     */
    public static function deliveryRefunds(): iterable
    {
        $whole = [['product:BOOK', 1, ReturnReason::ChangedMind], ['product:DICE', 1, ReturnReason::ChangedMind]];
        $fault = [['product:BOOK', 1, ReturnReason::Defective]];
        $mixed = [['product:BOOK', 1, ReturnReason::Defective], ['product:DICE', 1, ReturnReason::ChangedMind]];

        yield 'never, the whole order' => [DeliveryRefund::Never, $whole, 0];
        yield 'the shop at fault' => [DeliveryRefund::ShopsFault, $fault, 800];
        yield 'the shop at fault for one line only' => [DeliveryRefund::ShopsFault, [['product:DICE', 1, ReturnReason::Damaged], ['product:BOOK', 1, ReturnReason::Other]], 800];
        yield 'the shop not at fault, the order left' => [DeliveryRefund::ShopsFault, [['product:BOOK', 1, ReturnReason::Other]], 0];
        yield 'the shop not at fault, the whole order' => [DeliveryRefund::ShopsFault, $whole, 800];
        yield 'the whole order only' => [DeliveryRefund::WholeOrder, $whole, 800];
        yield 'the whole order only, the shop at fault' => [DeliveryRefund::WholeOrder, $fault, 0];
        yield 'the whole order only, the shop partly at fault' => [DeliveryRefund::WholeOrder, $mixed, 800];
    }

    /**
     * The delivery — 10.00, less a waiver of 2.00, an included one aside — comes back as the policy says.
     *
     * @param list<array{string, int, ReturnReason}> $asked
     */
    #[DataProvider('deliveryRefunds')]
    public function testTheDeliveryComesBackAsThePolicySays(DeliveryRefund $policy, array $asked, int $refund): void
    {
        $lines = array_map(fn (array $line): array => $this->ask(...$line), $asked);

        self::assertSame($refund, $this->valuation()->value($this->special(1, 1), $lines, [], $policy)['deliveryRefund']);
    }

    public function testTheDeliveryComesBackOncePerOrder(): void
    {
        $order = $this->special(1, 1);
        $first = $this->valuation()->value($order, [$this->ask('product:BOOK', 1, ReturnReason::Defective)], [], DeliveryRefund::ShopsFault);
        self::assertSame(800, $first['deliveryRefund']);

        $held = $this->held($order, $first);
        self::assertSame(0, $this->valuation()->value($order, [$this->ask('product:DICE', 1, ReturnReason::Defective)], [$held], DeliveryRefund::ShopsFault)['deliveryRefund']);

        $other = ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:BOOK', 1, ReturnReason::Other, 100, 1)], 0, new DateTimeImmutable());
        self::assertSame(800, $this->valuation()->value($order, [$this->ask('product:DICE', 1, ReturnReason::Other)], [$other], DeliveryRefund::ShopsFault)['deliveryRefund'], 'The other return brought back the book: the dice end the order.');
    }

    public function testADeliveryWaivedEntirelyGivesNothingBack(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-3', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'BOOK'), 'Book', Money::of(3_000, $cad), 'standard'), 1, Money::of(3_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(500, $cad)), Adjustment::of(AdjustmentType::Shipping, 'Waiver', Money::of(-700, $cad))]);
        $order->place(new DateTimeImmutable());

        self::assertSame(0, $this->valuation()->value($order, [$this->ask('product:BOOK', 1, ReturnReason::Defective)], [], DeliveryRefund::ShopsFault)['deliveryRefund']);
    }

    public function testTooManyUnitsAreRefused(): void
    {
        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('2 of product:BOOK cannot come back: 1 can.');

        $this->valuation()->value($this->special(1, 1), [$this->ask('product:BOOK', 2)], [], DeliveryRefund::Never);
    }

    /**
     * The units earlier returns hold, and what they were charged, add up over all of them.
     */
    public function testEveryHoldingReturnCounts(): void
    {
        $order = $this->special(3, 3);
        $book = [ReturnLine::of('product:BOOK', 1, ReturnReason::Other, 2_700, 3)];
        $dice = [ReturnLine::of('product:DICE', 1, ReturnReason::Other, 1_600, 3)];
        $heldBooks = [$this->holding($order, $book), $this->holding($order, $book)];

        try {
            $this->valuation()->value($order, [$this->ask('product:BOOK', 2)], $heldBooks, DeliveryRefund::Never);
            self::fail('Two units of three were held: two more cannot come back.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('2 of product:BOOK cannot come back: 1 can.', $returnRefused->getMessage());
        }

        $heldDice = [$this->holding($order, $dice, [self::SPECIAL => 500]), $this->holding($order, $dice, [self::SPECIAL => 500])];

        self::assertSame([self::SPECIAL => 1_000], $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], $heldDice, DeliveryRefund::Never)['specialCharges']);
    }

    /**
     * A special is charged for the units held so far even when this return brings back a line of no special, less
     * what earlier returns were charged.
     */
    public function testAReturnOfAnotherLineIsChargedForTheUnitsHeld(): void
    {
        $order = $this->orderOf(['BOOK' => 3_000, 'DICE' => 2_000, 'TEA' => 500], [
            [AdjustmentType::Promotion, 'BOOK', -600, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'DICE', -400, false, self::SPECIAL],
        ]);
        $held = $this->holding($order, [ReturnLine::of('product:BOOK', 1, ReturnReason::Other, 2_400, 1)]);

        self::assertSame([self::SPECIAL => 500], $this->valuation()->value($order, [$this->ask('product:TEA', 1)], [$held], DeliveryRefund::Never)['specialCharges']);
    }

    /**
     * Only a promotion that is not neutral, with an origin, takes something off and is a special counts.
     */
    public function testOnlyTheRealSpecialDiscountsOfALineCount(): void
    {
        $order = $this->orderOf(['BOOK' => 1_000, 'DICE' => 1_000], [
            [AdjustmentType::Promotion, 'BOOK', -300, false, self::SPECIAL],
            [AdjustmentType::Option, 'BOOK', -100, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'BOOK', -70, true, self::SPECIAL],
            [AdjustmentType::Promotion, 'BOOK', 50, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'BOOK', -20, false, null],
        ]);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        // 1000 − 300 − 100 + 50 − 20 on the line, and the 300 of the special back.
        self::assertSame([630 + 300], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
        self::assertSame([self::SPECIAL => 300], $valued['specialCharges']);
    }

    public function testASpecialThatTakesTheWholeBaseOffLeavesNoTaxToGiveBack(): void
    {
        $order = $this->orderOf(['BOOK' => 1_000], [[AdjustmentType::Promotion, 'BOOK', -1_000, false, self::SPECIAL]]);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        self::assertSame([1_000], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
    }

    public function testTwoSpecialDiscountsOnOneLineAddUp(): void
    {
        $order = $this->orderOf(['BOOK' => 1_000, 'DICE' => 1_000], [
            [AdjustmentType::Promotion, 'BOOK', -300, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'BOOK', -200, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'DICE', -100, false, self::SPECIAL],
        ]);
        $valued = $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never);

        self::assertSame([1_000], array_map(static fn (ReturnLine $line): int => $line->linePaid, $valued['lines']));
        self::assertSame([self::SPECIAL => 600], $valued['specialCharges']);
    }

    /**
     * 10.00 off a line of 10.00 leaves a base of 1.00 for the taxes: 0.15 on the book, from two taxes, and none on
     * the dice, whose special saves 9.00 and the tax on its 1.00 — nothing.
     */
    public function testTheTaxesOfALineAddUpAndAMissingOneIsZero(): void
    {
        $order = $this->orderOf(['BOOK' => 1_000, 'DICE' => 1_000], [
            [AdjustmentType::Promotion, 'BOOK', -900, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'DICE', -900, false, self::SPECIAL],
            [AdjustmentType::Tax, 'BOOK', 10, false, null],
            [AdjustmentType::Tax, 'BOOK', 5, false, null],
        ]);

        self::assertSame([1_150], array_map(static fn (ReturnLine $line): int => $line->linePaid, $this->valuation()->value($order, [$this->ask('product:BOOK', 1)], [], DeliveryRefund::Never)['lines']));
        self::assertSame([1_000], array_map(static fn (ReturnLine $line): int => $line->linePaid, $this->valuation()->value($order, [$this->ask('product:DICE', 1)], [], DeliveryRefund::Never)['lines']));
    }

    /**
     * The charges never exceed what the lines are worth, all specials together; each special broken is charged.
     */
    public function testEachSpecialBrokenIsChargedWithinWhatTheLinesAreWorth(): void
    {
        $other = 'promotion:trio';
        $order = $this->orderOf(['BOOK' => 3_000, 'PIN' => 200], [
            [AdjustmentType::Promotion, 'BOOK', -900, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'BOOK', -300, false, $other],
            [AdjustmentType::Promotion, 'PIN', -100, false, self::SPECIAL],
            [AdjustmentType::Promotion, 'PIN', -50, false, $other],
        ]);
        $valuation = new ReturnValuation(new Specials([self::SPECIAL, $other]), new Specials([self::SPECIAL, $other]));

        self::assertSame([self::SPECIAL => 200], $valuation->value($order, [$this->ask('product:PIN', 1)], [], DeliveryRefund::Never)['specialCharges']);
        self::assertSame([self::SPECIAL => 1_000, $other => 350], $valuation->value($order, [$this->ask('product:BOOK', 1), $this->ask('product:PIN', 1)], [], DeliveryRefund::Never)['specialCharges']);
    }

    /**
     * @param array<string, int>                                      $prices      each line's unit price, one unit
     * @param list<array{AdjustmentType, string, int, bool, ?string}> $adjustments type, line, amount, neutral, origin
     */
    private function orderOf(array $prices, array $adjustments): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);

        foreach ($prices as $sku => $price) {
            $order->add(Sellable::of(SellableReference::of('product', $sku), $sku, Money::of($price, $cad), 'standard'), 1, Money::of($price, $cad));
        }

        foreach (AdjustmentType::cases() as $type) {
            $own = array_filter($adjustments, static fn (array $adjustment): bool => $adjustment[0] === $type);

            if ([] !== $own) {
                $order->replaceAdjustments($type, array_values(array_map(static fn (array $adjustment): Adjustment => Adjustment::of($type, $adjustment[1], Money::of($adjustment[2], $cad), SellableReference::of('product', $adjustment[1]), $adjustment[3], $adjustment[4]), $own)));
            }
        }

        $order->place(new DateTimeImmutable());

        return $order;
    }

    /**
     * @param list<ReturnLine>   $lines
     * @param array<string, int> $charges
     */
    private function holding(Order $order, array $lines, array $charges = []): ReturnRequest
    {
        return ReturnRequest::request($order->number, $order->currency, $lines, 0, new DateTimeImmutable(), $charges);
    }

    private function valuation(): ReturnValuation
    {
        return new ReturnValuation(new Specials([self::SPECIAL]), new Specials([self::SPECIAL]));
    }

    /**
     * @return array{line: string, quantity: int, reason: ReturnReason}
     */
    private function ask(string $line, int $quantity, ReturnReason $reason = ReturnReason::ChangedMind): array
    {
        return ['line' => $line, 'quantity' => $quantity, 'reason' => $reason];
    }

    /**
     * @param array{lines: list<ReturnLine>, specialCharges: array<string, int>, deliveryRefund: int} $valued
     */
    private function held(Order $order, array $valued): ReturnRequest
    {
        return ReturnRequest::request($order->number, $order->currency, $valued['lines'], 0, new DateTimeImmutable(), $valued['specialCharges'], $valued['deliveryRefund']);
    }

    /**
     * @param array{lines: list<ReturnLine>, specialCharges: array<string, int>, deliveryRefund: int} $valued
     */
    private function value(Order $order, array $valued): int
    {
        return $this->held($order, $valued)->value()->minorUnits;
    }

    /**
     * Books at 30.00 and dice at 20.00, with a special of 10.00 off per pair spread 6.00 and 4.00, 10 % off the books,
     * and a delivery of 10.00 less a 2.00 waiver, a neutral charge aside; taxed at 15 % on request.
     */
    private function special(int $books, int $dice, bool $taxed = false): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);
        $book = SellableReference::of('product', 'BOOK');
        $die = SellableReference::of('product', 'DICE');
        $order->add(Sellable::of($book, 'Book', Money::of(3_000, $cad), 'standard'), $books, Money::of(3_000, $cad));
        $order->add(Sellable::of($die, 'Dice', Money::of(2_000, $cad), 'standard'), $dice, Money::of(2_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, '10 %', Money::of(-300 * $books, $cad), $book, origin: 'promotion:ten'),
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-600 * $books, $cad), $book, origin: self::SPECIAL),
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-400 * $dice, $cad), $die, origin: self::SPECIAL),
            Adjustment::of(AdjustmentType::Promotion, 'Neutral', Money::zero($cad), $die, neutral: true, origin: self::SPECIAL),
        ]);

        if ($taxed) {
            $order->replaceAdjustments(AdjustmentType::Tax, [
                Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(315 * $books, $cad), $book),
                Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(240 * $dice, $cad), $die),
                Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(999, $cad), $die, true),
            ]);
        }

        $order->replaceAdjustments(AdjustmentType::Shipping, [
            Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(1_000, $cad)),
            Adjustment::of(AdjustmentType::Shipping, 'Waiver', Money::of(-200, $cad)),
            Adjustment::of(AdjustmentType::Shipping, 'Included', Money::of(500, $cad), neutral: true),
        ]);
        $order->place(new DateTimeImmutable());

        return $order;
    }
}
