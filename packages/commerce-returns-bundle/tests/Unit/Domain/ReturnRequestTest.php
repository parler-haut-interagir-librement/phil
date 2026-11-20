<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnRequest::class)]
#[CoversClass(ReturnLine::class)]
#[CoversClass(ReturnState::class)]
#[CoversClass(ReturnReason::class)]
#[CoversClass(ItemCondition::class)]
#[CoversClass(ReturnRefused::class)]
final class ReturnRequestTest extends TestCase
{
    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testAReturnIsRequestedForItsLines(): void
    {
        $return = $this->request();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $return->id);
        self::assertSame(['A-1', 'CAD', 100_000, $this->at, ReturnState::Requested, null, null, $this->at, 1], [
            $return->orderNumber, $return->currency()->code, $return->restockingFee, $return->requestedAt, $return->state(), $return->resolution(), $return->note(), $return->changedAt(), $return->version(),
        ]);
        self::assertSame([
            ['line' => 'product:TEA', 'quantity' => 2, 'reason' => 'defective', 'line_paid' => 1_725, 'line_quantity' => 3, 'returned_before' => 0, 'condition' => null],
            ['line' => 'product:MUG', 'quantity' => 1, 'reason' => 'changed_mind', 'line_paid' => 1_380, 'line_quantity' => 1, 'returned_before' => 0, 'condition' => null],
        ], array_map(static fn (ReturnLine $line): array => $line->toArray(), $return->lines()));
        self::assertSame([2, 1, 0], [$return->quantityOf('product:TEA'), $return->quantityOf('product:MUG'), $return->quantityOf('product:CUP')]);
    }

    public function testItsValueTakesTheFeeOnTheCustomersChoices(): void
    {
        // Two thirds of 17.25 is 11.50; 13.80 less 10 % is 12.42.
        self::assertSame(1_150 + 1_242, $this->request()->value()->minorUnits);
        self::assertSame('CAD', $this->request()->value()->currency->code);
    }

    public function testWithoutFeeTheValueIsTheLines(): void
    {
        $return = ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:MUG', 1, ReturnReason::Other, 1_380, 1)], 0, $this->at);

        self::assertSame(1_380, $return->value()->minorUnits);
        self::assertSame(1_242, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:MUG', 1, ReturnReason::Other, 1_380, 1)], 100_000, $this->at)->value()->minorUnits);
        self::assertSame(0, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:MUG', 1, ReturnReason::Other, 1_380, 1)], 1_000_000, $this->at)->value()->minorUnits);
        self::assertSame(1_379, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:MUG', 1, ReturnReason::Other, 1_380, 1)], 363, $this->at)->value()->minorUnits);
        self::assertSame(1_380, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:MUG', 1, ReturnReason::Other, 1_380, 1)], 362, $this->at)->value()->minorUnits);
    }

    public function testTheFeeRoundsToTheNearestMinorUnit(): void
    {
        self::assertSame(499_999, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:A', 1, ReturnReason::Other, 499_999, 1)], 1, $this->at)->value()->minorUnits);
        self::assertSame(499_999, ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:A', 1, ReturnReason::Other, 500_000, 1)], 1, $this->at)->value()->minorUnits);
    }

    public function testAMissingLineIsWorthNothingAndTheOthersCount(): void
    {
        $return = $this->request();
        $return->authorize($this->at);
        $return->receive(['product:TEA' => ItemCondition::Missing, 'product:MUG' => ItemCondition::Resellable], $this->at);

        self::assertSame(1_242, $return->value()->minorUnits);
    }

    public function testAUnitsShareRoundsHalfUp(): void
    {
        self::assertSame(1, ReturnLine::of('product:A', 1, ReturnReason::Other, 3, 2)->value() - 1);
        self::assertSame(2, ReturnLine::of('product:A', 1, ReturnReason::Other, 3, 2)->value());
        self::assertSame(3, ReturnLine::of('product:A', 1, ReturnReason::Other, 10, 3)->value());
        self::assertSame(7, ReturnLine::of('product:A', 2, ReturnReason::Other, 10, 3)->value());
        self::assertSame(10, ReturnLine::of('product:A', 3, ReturnReason::Other, 10, 3)->value());
        self::assertSame(0, ReturnLine::of('product:A', 1, ReturnReason::Other, 0, 1)->value());
    }

    /**
     * A line of 2.00 for 3 units, returned one unit at a time: 0.67, 0.66, 0.67, never 2.01; a line of 1.00 gives
     * 0.33, 0.34, 0.33, never 0.99.
     */
    public function testUnitsReturnedInPartsAddUpToTheLine(): void
    {
        self::assertSame([67, 66, 67], array_map(static fn (int $before): int => ReturnLine::of('product:A', 1, ReturnReason::Other, 200, 3, $before)->value(), [0, 1, 2]));
        self::assertSame([33, 34, 33], array_map(static fn (int $before): int => ReturnLine::of('product:A', 1, ReturnReason::Other, 100, 3, $before)->value(), [0, 1, 2]));
        self::assertSame(133, ReturnLine::of('product:A', 2, ReturnReason::Other, 200, 3, 1)->value());
        self::assertSame(1, ReturnLine::of('product:A', 2, ReturnReason::Other, 200, 3, 1)->returnedBefore);
    }

    public function testTheWayOfAReturn(): void
    {
        $return = $this->request();
        $later = $this->at->modify('+1 day');

        $return->authorize($later);
        self::assertSame([ReturnState::Authorized, $later], [$return->state(), $return->changedAt()]);

        $return->receive(['product:TEA' => ItemCondition::Resellable, 'product:MUG' => ItemCondition::Missing], $later);
        self::assertSame(ReturnState::Received, $return->state());
        self::assertSame([ItemCondition::Resellable, ItemCondition::Missing], array_map(static fn (ReturnLine $line): ?ItemCondition => $line->condition, $return->lines()));
        self::assertSame(1_150, $return->value()->minorUnits);

        $return->resolve(Resolution::Refund, $later);
        self::assertSame([ReturnState::Resolved, Resolution::Refund], [$return->state(), $return->resolution()]);
    }

    public function testAReceptionNeedsEveryCondition(): void
    {
        $return = $this->request();
        $return->authorize($this->at);

        try {
            $return->receive(['product:TEA' => ItemCondition::Damaged], $this->at);
            self::fail('A line was received without its condition.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s needs the condition of product:MUG.', $return->id), $returnRefused->getMessage());
        }

        self::assertSame(ReturnState::Authorized, $return->state());
        self::assertNull($return->lines()[0]->condition);
    }

    /**
     * @return iterable<string, array{list<string>, string, string}>
     */
    public static function badSteps(): iterable
    {
        yield 'receive before authorizing' => [[], 'receive', 'requested to received'];
        yield 'resolve before receiving' => [['authorize'], 'resolve', 'authorized to resolved'];
        yield 'authorize twice' => [['authorize'], 'authorize', 'authorized to authorized'];
        yield 'reject once received' => [['authorize', 'receive'], 'reject', 'received to rejected'];
        yield 'cancel once received' => [['authorize', 'receive'], 'cancel', 'received to cancelled'];
        yield 'resolve twice' => [['authorize', 'receive', 'resolve'], 'resolve', 'resolved to resolved'];
        yield 'authorize once rejected' => [['reject'], 'authorize', 'rejected to authorized'];
        yield 'reject once cancelled' => [['cancel'], 'reject', 'cancelled to rejected'];
    }

    /**
     * @param list<string> $steps
     */
    #[DataProvider('badSteps')]
    public function testAReturnTakesItsStepsInOrder(array $steps, string $step, string $message): void
    {
        $return = $this->request();

        foreach ([...$steps, $step] as $index => $name) {
            if ($index === \count($steps)) {
                $this->expectException(ReturnRefused::class);
                $this->expectExceptionMessageIsOrContains(\sprintf('The return %s cannot go from %s.', $return->id, $message));
            }

            match ($name) {
                'authorize' => $return->authorize($this->at),
                'receive' => $return->receive(['product:TEA' => ItemCondition::Resellable, 'product:MUG' => ItemCondition::Resellable], $this->at),
                'resolve' => $return->resolve(Resolution::Refund, $this->at),
                'reject' => $return->reject('No', $this->at),
                'cancel' => $return->cancel($this->at),
                default => self::fail($name),
            };
        }
    }

    public function testARejectionSaysWhy(): void
    {
        $return = $this->request();
        $return->authorize($this->at);
        $return->reject(str_repeat('é', 255), $this->at);
        self::assertSame([ReturnState::Rejected, str_repeat('é', 255)], [$return->state(), $return->note()]);

        foreach ([' ', str_repeat('é', 256)] as $note) {
            try {
                $this->request()->reject($note, $this->at);
                self::fail('A rejection without reason was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A rejection says why, in 1 to 255 characters.', $invalid->getMessage());
            }
        }

        $cancelled = $this->request();
        $cancelled->cancel($this->at);
        self::assertSame(ReturnState::Cancelled, $cancelled->state());
    }

    public function testARequestIsChecked(): void
    {
        $line = ReturnLine::of('product:TEA', 1, ReturnReason::Defective, 575, 1);
        $cases = [
            'A return of A-1 takes back at least one line.' => static fn (): ReturnRequest => ReturnRequest::request('A-1', Currency::of('CAD'), [], 0, new DateTimeImmutable()),
            'A return of A-1 names each line once.' => static fn (): ReturnRequest => ReturnRequest::request('A-1', Currency::of('CAD'), [$line, $line], 0, new DateTimeImmutable()),
            'A restocking fee is 0 to 1000000 parts per million; -1 is refused.' => static fn (): ReturnRequest => ReturnRequest::request('A-1', Currency::of('CAD'), [$line], -1, new DateTimeImmutable()),
            'A restocking fee is 0 to 1000000 parts per million; 1000001 is refused.' => static fn (): ReturnRequest => ReturnRequest::request('A-1', Currency::of('CAD'), [$line], 1_000_001, new DateTimeImmutable()),
        ];

        foreach ($cases as $message => $make) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame(1_000_000, ReturnRequest::request('A-1', Currency::of('CAD'), [$line], 1_000_000, new DateTimeImmutable())->restockingFee);
    }

    public function testALineIsChecked(): void
    {
        $cases = [
            'A return line takes back 1 to 3 units of product:TEA; 0 is refused.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 0, ReturnReason::Other, 100, 3),
            'A return line takes back 1 to 3 units of product:TEA; 4 is refused.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 4, ReturnReason::Other, 100, 3),
            'The line product:TEA came to zero or more.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 1, ReturnReason::Other, -1, 3),
            'A return line takes back 1 to 1 units of product:TEA; 2 is refused.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 2, ReturnReason::Other, 100, 3, 2),
            'Earlier returns of product:TEA took 0 to 2 units; 3 is refused.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 1, ReturnReason::Other, 100, 3, 3),
            'Earlier returns of product:TEA took 0 to 2 units; -1 is refused.' => static fn (): ReturnLine => ReturnLine::of('product:TEA', 1, ReturnReason::Other, 100, 3, -1),
        ];

        foreach ($cases as $message => $make) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame(3, ReturnLine::of('product:TEA', 3, ReturnReason::Other, 100, 3)->quantity);
        $read = ReturnLine::fromArray(['line' => 'product:TEA', 'quantity' => 1, 'reason' => 'damaged', 'line_paid' => 5, 'line_quantity' => 2, 'condition' => 'damaged']);
        self::assertSame(['product:TEA', 1, ReturnReason::Damaged, 5, 2, 0, ItemCondition::Damaged], [$read->line, $read->quantity, $read->reason, $read->linePaid, $read->lineQuantity, $read->returnedBefore, $read->condition]);
        self::assertSame(2, ReturnLine::fromArray(['line' => 'product:TEA', 'quantity' => 1, 'reason' => 'damaged', 'line_paid' => 9, 'line_quantity' => 3, 'returned_before' => 2, 'condition' => null])->returnedBefore);
    }

    public function testTheEnumsSayWhatTheyMean(): void
    {
        self::assertSame([true, true, true, true, true, false, false, false], array_map(static fn (ReturnState $state): bool => $state->holds(), ReturnState::cases()));
        self::assertSame([true, true, true, true, false, false], array_map(static fn (ReturnReason $reason): bool => $reason->isShopsFault(), ReturnReason::cases()));
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'No return is known as r-1.',
            'The order A-1 is not placed: nothing of it can come back.',
            'The order A-1 is past its 30 days of returns.',
            'The order A-1 has no line product:CUP.',
            '3 of product:TEA cannot come back: 1 can.',
            'Store credit needs phil/commerce-stored-value-bundle, and a customer to credit.',
            'The order B-1 cannot take an exchange: it is not placed, or in another currency.',
        ], [
            ReturnRefused::unknown('r-1')->getMessage(),
            ReturnRefused::notPlaced('A-1')->getMessage(),
            ReturnRefused::tooLate('A-1', 30)->getMessage(),
            ReturnRefused::notInOrder('A-1', 'product:CUP')->getMessage(),
            ReturnRefused::tooMany('product:TEA', 3, 1)->getMessage(),
            ReturnRefused::noStoreCredit()->getMessage(),
            ReturnRefused::notAnExchange('B-1')->getMessage(),
        ]);
        self::assertInstanceOf(DomainError::class, ReturnRefused::unknown('r-1'));
    }

    private function request(): ReturnRequest
    {
        return ReturnRequest::request('A-1', Currency::of('CAD'), [
            ReturnLine::of('product:TEA', 2, ReturnReason::Defective, 1_725, 3),
            ReturnLine::of('product:MUG', 1, ReturnReason::ChangedMind, 1_380, 1),
        ], 100_000, $this->at);
    }
}
