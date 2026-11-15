<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManualDiscount::class)]
final class ManualDiscountTest extends TestCase
{
    public function testAnAmountOffIsKeptWithItsReasonAndWhoGrantedIt(): void
    {
        $line = SellableReference::of('product', 'dice');
        $discount = ManualDiscount::amount('m-1', $this->cad(300), 'Damaged box', 'employee-7', $line);

        self::assertSame('m-1', $discount->key);
        self::assertSame($line, $discount->line);
        self::assertSame(300, $discount->amount?->minorUnits);
        self::assertNull($discount->basisPoints);
        self::assertSame('Damaged box', $discount->reason);
        self::assertSame('employee-7', $discount->grantedBy);
        self::assertSame(['key' => 'm-1', 'line' => 'product:dice', 'amount' => 300, 'currency' => 'CAD', 'basis_points' => null, 'reason' => 'Damaged box', 'granted_by' => 'employee-7'], $discount->toArray());
        self::assertEquals($discount, ManualDiscount::fromArray($discount->toArray()));
    }

    public function testAShareOffOnTheWholeOrderIsStoredToo(): void
    {
        $discount = ManualDiscount::share('loyal', 1_500, 'Regular', 'employee-7');

        self::assertSame(['key' => 'loyal', 'line' => null, 'amount' => null, 'currency' => null, 'basis_points' => 1_500, 'reason' => 'Regular', 'granted_by' => 'employee-7'], $discount->toArray());
        self::assertEquals($discount, ManualDiscount::fromArray($discount->toArray()));
    }

    public function testAnAmountNeverTakesMoreThanItsBase(): void
    {
        $discount = ManualDiscount::amount('m', $this->cad(300), 'R', 'e');

        self::assertSame(300, $discount->of($this->cad(1_000))->minorUnits);
        self::assertSame(300, $discount->of($this->cad(300))->minorUnits);
        self::assertSame(200, $discount->of($this->cad(200))->minorUnits);
        self::assertTrue($discount->of($this->cad(0))->isZero());
        self::assertTrue($discount->of($this->cad(-50))->isZero());
    }

    public function testAShareIsRoundedDown(): void
    {
        self::assertSame(149, ManualDiscount::share('m', 1_500, 'R', 'e')->of($this->cad(999))->minorUnits);
        self::assertSame(999, ManualDiscount::share('m', 10_000, 'R', 'e')->of($this->cad(999))->minorUnits);
        self::assertSame(0, ManualDiscount::share('m', 1, 'R', 'e')->of($this->cad(99))->minorUnits);
        self::assertSame(1, ManualDiscount::share('m', 1, 'R', 'e')->of($this->cad(10_000))->minorUnits);
    }

    public function testTheBoundsOfAShareAreAccepted(): void
    {
        self::assertSame(1, ManualDiscount::share('m', 1, 'R', 'e')->basisPoints);
        self::assertSame(10_000, ManualDiscount::share('m', 10_000, 'R', 'e')->basisPoints);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedShares(): iterable
    {
        yield 'zero' => [0];
        yield 'more than all' => [10_001];
    }

    #[DataProvider('refusedShares')]
    public function testAShareIsOneBasisPointToAll(int $basisPoints): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The manual discount "m" takes 1 to 10000 basis points off.');

        ManualDiscount::share('m', $basisPoints, 'R', 'e');
    }

    public function testAnAmountIsPositive(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The manual discount "m" takes a positive amount off.');

        ManualDiscount::amount('m', $this->cad(0), 'R', 'e');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('k', 33)];
        yield 'a space' => ['a b'];
        yield 'a colon' => ['a:b'];
    }

    #[DataProvider('refusedKeys')]
    public function testAKeyIsShortAndPlain(string $key): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not the key of a manual discount: 1 to 32 letters, digits, "_" or "-".', $key));

        ManualDiscount::share($key, 100, 'R', 'e');
    }

    public function testTheLongestKeyAndReasonAreAccepted(): void
    {
        $discount = ManualDiscount::share(str_repeat('K', 32), 100, str_repeat('r', 255), 'e');

        self::assertSame(32, \strlen($discount->key));
        self::assertSame(255, mb_strlen($discount->reason));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedReasons(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('r', 256)];
        yield 'two lines' => ["a\nb"];
    }

    #[DataProvider('refusedReasons')]
    public function testAReasonIsOneLine(string $reason): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A manual discount has a reason: 1 to 255 characters on one line.');

        ManualDiscount::amount('m', $this->cad(1), $reason, 'e');
    }

    public function testWhoGrantedItIsAStableIdentifier(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not the stable identifier of a employee');

        ManualDiscount::amount('m', $this->cad(1), 'R', 'bob@example.com');
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    public function testAStoredShareIsReadBackWhateverItsCurrencyColumnHolds(): void
    {
        $stored = ['key' => 'm', 'line' => null, 'amount' => null, 'currency' => 'CAD', 'basis_points' => 500, 'reason' => 'R', 'granted_by' => 'e'];
        $discount = ManualDiscount::fromArray($stored);

        self::assertSame(500, $discount->basisPoints);
        self::assertNull($discount->amount);
    }

    public function testAStoredDiscountOfNeitherAmountNorShareIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The stored manual discount "m" holds neither an amount nor a share.');

        ManualDiscount::fromArray(['key' => 'm', 'line' => null, 'amount' => null, 'currency' => null, 'basis_points' => null, 'reason' => 'R', 'granted_by' => 'e']);
    }
}
