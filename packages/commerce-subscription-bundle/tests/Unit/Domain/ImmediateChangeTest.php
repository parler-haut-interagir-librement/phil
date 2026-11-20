<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\ImmediateChange;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImmediateChange::class)]
final class ImmediateChangeTest extends TestCase
{
    public function testItChargesTheShareOfTheTimeLeft(): void
    {
        $change = ImmediateChange::of('premium', new DateTimeImmutable('2026-04-11'), 20, 30, $this->cad(667));

        self::assertSame('premium', $change->offerCode);
        self::assertSame('2026-04-11', $change->at->format('Y-m-d'));
        self::assertSame(20, $change->secondsLeft);
        self::assertSame(30, $change->periodSeconds);
        self::assertSame(667, $change->credit->minorUnits);
        self::assertFalse($change->startsAPeriod());
        self::assertSame(2_000, $change->charge($this->cad(3_000))->minorUnits);
        self::assertSame(667, $change->charge($this->cad(1_000))->minorUnits, 'a tie goes to the time left');
        self::assertSame(0, $change->charge($this->cad(0))->minorUnits);
    }

    public function testAWholePeriodLeftChargesTheWholePrice(): void
    {
        self::assertSame(3_000, ImmediateChange::of('premium', new DateTimeImmutable(), 30, 30, $this->cad(0))->charge($this->cad(3_000))->minorUnits);
        self::assertSame(1, ImmediateChange::of('premium', new DateTimeImmutable(), 1, 30, $this->cad(0))->charge($this->cad(30))->minorUnits);
    }

    public function testAChangeThatStartsAPeriodChargesItWhole(): void
    {
        $change = ImmediateChange::of('annual', new DateTimeImmutable(), 20, null, $this->cad(0));

        self::assertTrue($change->startsAPeriod());
        self::assertSame(30_000, $change->charge($this->cad(30_000))->minorUnits);
    }

    /**
     * @return iterable<string, array{int, ?int, int}>
     */
    public static function invalid(): iterable
    {
        yield 'no time left' => [0, 30, 0];
        yield 'more than the period' => [31, 30, 0];
        yield 'a negative credit' => [10, 30, -1];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidChangeIsRefused(int $left, ?int $period, int $credit): void
    {
        $this->expectException(InvalidValue::class);

        ImmediateChange::of('premium', new DateTimeImmutable(), $left, $period, $this->cad($credit));
    }

    public function testASecondLeftWithoutAPeriodIsValid(): void
    {
        self::assertSame(1, ImmediateChange::of('annual', new DateTimeImmutable(), 1, null, $this->cad(0))->secondsLeft);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
