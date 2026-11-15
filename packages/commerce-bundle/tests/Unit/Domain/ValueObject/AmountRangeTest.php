<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AmountRange::class)]
final class AmountRangeTest extends TestCase
{
    public function testARangeKeepsItsBounds(): void
    {
        $range = AmountRange::of($this->cad(0), $this->cad(0));

        self::assertSame([0, 0], [$range->minimum->minorUnits, $range->maximum->minorUnits]);
        self::assertSame(5_000, AmountRange::of($this->cad(500), $this->cad(5_000))->maximum->minorUnits);
    }

    /**
     * @return iterable<string, array{Money, Money, string}>
     */
    public static function refused(): iterable
    {
        $cad = static fn (int $minor): Money => Money::of($minor, Currency::of('CAD'));

        yield 'a negative minimum' => [$cad(-1), $cad(10), 'An amount range starts at zero or more.'];
        yield 'a maximum below' => [$cad(10), $cad(9), 'An amount range ends at its minimum or above, in the same currency.'];
        yield 'another currency' => [$cad(10), Money::of(20, Currency::of('EUR')), 'An amount range ends at its minimum or above, in the same currency.'];
    }

    #[DataProvider('refused')]
    public function testABrokenRangeIsRefused(Money $minimum, Money $maximum, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs($message);

        AmountRange::of($minimum, $maximum);
    }

    public function testAValueIsReadAsMinorUnitsOfTheRangesCurrency(): void
    {
        $range = AmountRange::of(Money::of(0, Currency::of('JPY')), Money::of(10_000, Currency::of('JPY')));

        self::assertSame([1_200, 'JPY'], [$range->read('1200')?->minorUnits, $range->read('1200')?->currency->code]);
        self::assertSame(0, $range->read('0')?->minorUnits);
        self::assertSame(999_999_999_999_999_999, $range->read('999999999999999999')?->minorUnits);

        foreach (['', '012', '-1', '+1', '1.5', '1 000', "12\n", '1000000000000000000', 'twelve'] as $value) {
            self::assertNull($range->read($value), $value);
        }
    }

    public function testAnAmountIsWithinTheBoundsOrSaysWhichOne(): void
    {
        $range = AmountRange::of($this->cad(500), $this->cad(5_000));

        self::assertNull($range->refusal($this->cad(500)));
        self::assertNull($range->refusal($this->cad(5_000)));
        self::assertSame('the amount is 5.00 or more', $range->refusal($this->cad(499)));
        self::assertSame('the amount is 50.00 or less', $range->refusal($this->cad(5_001)));
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
