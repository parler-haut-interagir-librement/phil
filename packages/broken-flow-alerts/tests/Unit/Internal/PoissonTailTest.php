<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit\Internal;

use Phil\BrokenFlowAlerts\Internal\PoissonTail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PoissonTail::class)]
final class PoissonTailTest extends TestCase
{
    /**
     * @return iterable<string, array{int, float, float}>
     */
    public static function tails(): iterable
    {
        yield 'nothing where one is expected' => [0, 1.0, exp(-1.0)];
        yield 'nothing where ten are expected' => [0, 10.0, exp(-10.0)];
        yield 'one where four are expected' => [1, 4.0, 5.0 * exp(-4.0)];
        yield 'two where three are expected' => [2, 3.0, 8.5 * exp(-3.0)];
        yield 'the mean itself' => [5, 5.0, 1.0];
        yield 'above the mean' => [9, 5.0, 1.0];
        yield 'nothing expected' => [0, 0.0, 1.0];
        yield 'a negative count' => [-1, 3.0, 0.0];
    }

    #[DataProvider('tails')]
    public function testTheLowerTail(int $observed, float $mean, float $tail): void
    {
        self::assertEqualsWithDelta($tail, PoissonTail::atMost($observed, $mean), 1e-12);
    }

    /**
     * Where `exp(-mean)` alone underflows to zero, the sum in logarithms still finds the tail: about one half just
     * under a mean of a thousand.
     */
    public function testALargeMeanDoesNotUnderflow(): void
    {
        $tail = PoissonTail::atMost(999, 1000.0);

        self::assertGreaterThan(0.49, $tail);
        self::assertLessThan(0.5, $tail);
    }

    public function testAVeryLargeCountUsesTheChernoffBound(): void
    {
        $mean = 20_000.0;
        $observed = PoissonTail::EXACT_UP_TO + 1;

        self::assertEqualsWithDelta(
            exp(-$mean + $observed + $observed * log($mean / $observed)),
            PoissonTail::atMost($observed, $mean),
            1e-300,
        );
        self::assertLessThan(1e-300, PoissonTail::atMost($observed, $mean));
    }

    public function testTheLastExactCountIsSummed(): void
    {
        // The exact tail of a count just under the mean is close to one half, far above the Chernoff bound.
        self::assertGreaterThan(0.4, PoissonTail::atMost(PoissonTail::EXACT_UP_TO, PoissonTail::EXACT_UP_TO + 1.0));

        // Far under the mean, the exact tail is about a tenth of the bound: the sum, not the bound, answers.
        $mean = 10_500.0;
        $observed = PoissonTail::EXACT_UP_TO;
        $bound = exp(-$mean + $observed + $observed * log($mean / $observed));

        self::assertEqualsWithDelta(4.498e-7, PoissonTail::atMost($observed, $mean), 1e-10);
        self::assertLessThan($bound / 10, PoissonTail::atMost($observed, $mean));
    }

    public function testTheBoundJustOverTheLastExactCount(): void
    {
        $mean = 10_500.0;
        $observed = PoissonTail::EXACT_UP_TO + 1;

        self::assertEqualsWithDelta(exp(-$mean + $observed + $observed * log($mean / $observed)), PoissonTail::atMost($observed, $mean), 1e-15);
        self::assertGreaterThan(5e-6, PoissonTail::atMost($observed, $mean));
    }
}
