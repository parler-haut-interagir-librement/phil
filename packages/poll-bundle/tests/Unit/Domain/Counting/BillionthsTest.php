<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Domain\Counting;

use InvalidArgumentException;
use Phil\PollBundle\Domain\Counting\Billionths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Billionths::class)]
final class BillionthsTest extends TestCase
{
    public function testANumberIsWrittenWithItsNineDecimals(): void
    {
        self::assertSame('0.000000000', Billionths::of(0)->toString());
        self::assertSame('0.000000001', Billionths::of(1)->toString());
        self::assertSame('4.714288797', (string) Billionths::of(4_714_288_797));
        self::assertSame('12.000000000', Billionths::whole(12)->toString());
        self::assertSame(1_000_000_000, Billionths::whole(1)->numerator);
        self::assertSame(1_000_000_000_000_000_000, Billionths::whole(1_000_000_000)->numerator);
        self::assertSame(1_000_000_000, Billionths::SCALE);
        self::assertTrue(Billionths::of(5)->equals(Billionths::of(5)));
        self::assertFalse(Billionths::of(5)->equals(Billionths::of(6)));
    }

    public function testANegativeOrTooLargeNumberIsRefused(): void
    {
        foreach ([static fn (): Billionths => Billionths::of(-1), static fn (): Billionths => Billionths::whole(-1), static fn (): Billionths => Billionths::whole(1_000_000_001)] as $make) {
            try {
                $make();
                self::fail('A number out of range was accepted.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertMatchesRegularExpression('/^A count holds (no negative number, not -\d+ billionths|at most 1000000000 whole units, not 1000000001)\.$/', $invalidArgumentException->getMessage());
            }
        }
    }
}
