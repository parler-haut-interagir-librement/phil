<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit;

use InvalidArgumentException;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Exception\UsageMeterException;
use Phil\UsageMeter\Limit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limit::class)]
#[CoversClass(InvalidUsage::class)]
final class LimitTest extends TestCase
{
    public function testANumberAllowsUpToItself(): void
    {
        $limit = Limit::of(10);

        self::assertSame(10, $limit->value);
        self::assertFalse($limit->isUnlimited());
        self::assertTrue($limit->allows(7, 3));
        self::assertFalse($limit->allows(7, 4));
        self::assertTrue($limit->allows(10, 0));
        self::assertFalse($limit->allows(11, 0));
        self::assertSame(3, $limit->remaining(7));
        self::assertSame(0, $limit->remaining(10));
        self::assertSame(0, $limit->remaining(12));
        self::assertSame('10', $limit->toString());
    }

    public function testZeroAllowsNothing(): void
    {
        self::assertFalse(Limit::of(0)->allows(0, 1));
        self::assertTrue(Limit::of(0)->allows(0, 0));
    }

    public function testNoLimitAllowsEverything(): void
    {
        $limit = Limit::unlimited();

        self::assertNull($limit->value);
        self::assertTrue($limit->isUnlimited());
        self::assertTrue($limit->allows(\PHP_INT_MAX - 1, 1));
        self::assertNull($limit->remaining(5));
        self::assertSame('unlimited', $limit->toString());
    }

    public function testALimitIsNotNegative(): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains('A limit is zero or more; got -1.');

        Limit::of(-1);
    }

    public function testTheInvalidUsageIsAnExceptionOfTheLibrary(): void
    {
        self::assertInstanceOf(UsageMeterException::class, InvalidUsage::because('x'));
        self::assertInstanceOf(InvalidArgumentException::class, InvalidUsage::because('x'));
    }

    public function testTheMoreGenerousOfTwoLimits(): void
    {
        self::assertSame(20, Limit::of(5)->orHigher(Limit::of(20))->value);
        self::assertSame(20, Limit::of(20)->orHigher(Limit::of(5))->value);
        self::assertTrue(Limit::unlimited()->orHigher(Limit::of(5))->isUnlimited());
        self::assertTrue(Limit::of(5)->orHigher(Limit::unlimited())->isUnlimited());
    }

    /**
     * @return iterable<string, array{string, int|false|null}>
     */
    public static function texts(): iterable
    {
        yield 'unlimited' => ['unlimited', null];
        yield 'zero' => ['0', 0];
        yield 'a number' => ['250', 250];
        yield 'eighteen digits' => ['999999999999999999', 999999999999999999];
        yield 'nineteen digits' => ['1000000000000000000', false];
        yield 'leading zero' => ['05', false];
        yield 'sign' => ['+5', false];
        yield 'negative' => ['-5', false];
        yield 'empty' => ['', false];
        yield 'word' => ['many', false];
        yield 'capitalised' => ['Unlimited', false];
        yield 'trailing newline' => ["5\n", false];
        yield 'prefix' => ['x5', false];
        yield 'suffix' => ['5x', false];
    }

    #[DataProvider('texts')]
    public function testALimitIsReadFromText(string $text, int|false|null $expected): void
    {
        $limit = Limit::parse($text);

        if (false === $expected) {
            self::assertNull($limit);

            return;
        }

        self::assertNotNull($limit);
        self::assertSame($expected, $limit->value);
    }
}
