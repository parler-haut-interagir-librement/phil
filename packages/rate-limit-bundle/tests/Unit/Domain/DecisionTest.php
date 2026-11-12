<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Domain;

use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decision::class)]
#[CoversClass(HeaderStyle::class)]
final class DecisionTest extends TestCase
{
    public function testAnAcceptedDecisionHasNoWait(): void
    {
        self::assertEquals(new Decision(true, 10, 4, 30, 0, 60), Decision::accepted(10, 4, 30, 60));
        self::assertEquals(new Decision(true, null, 0, 0), Decision::accepted(null, 0, 0));
    }

    public function testARefusedDecisionWaits(): void
    {
        self::assertEquals(new Decision(false, 10, 0, 30, 12, 60), Decision::refused(10, 0, 30, 12, 60));
        self::assertEquals(new Decision(false, 10, 0, 30, 12), Decision::refused(10, 0, 30, 12));
    }

    public function testANamedDecisionKeepsTheRest(): void
    {
        $decision = Decision::refused(10, 1, 30, 12, 60)->named('api');

        self::assertEquals(new Decision(false, 10, 1, 30, 12, 60, 'api'), $decision);
        self::assertSame('', Decision::accepted(1, 0, 0)->limiter);
    }

    public function testTheTighterDecisionLeavesLess(): void
    {
        $two = Decision::accepted(10, 2, 0);
        $three = Decision::accepted(5, 3, 0);
        $free = Decision::accepted(null, 0, 0);

        self::assertTrue($two->isTighterThan(null));
        self::assertTrue($two->isTighterThan($three));
        self::assertFalse($three->isTighterThan($two));
        self::assertFalse($two->isTighterThan($two));
        self::assertTrue($two->isTighterThan($free));
        self::assertFalse($free->isTighterThan(null));
        self::assertFalse($free->isTighterThan($two));
    }

    /**
     * @return iterable<string, array{int|null, int, int, int, int|null}>
     */
    public static function invalid(): iterable
    {
        yield 'negative remaining' => [10, -1, 0, 0, null];
        yield 'negative reset' => [10, 0, -1, 0, null];
        yield 'negative wait' => [10, 0, 0, -1, null];
        yield 'empty window' => [10, 0, 0, 0, 0];
        yield 'empty limit' => [0, 0, 0, 0, null];
    }

    #[DataProvider('invalid')]
    public function testCountsAreNeverNegative(?int $limit, int $remaining, int $reset, int $wait, ?int $window): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because('A decision counts from zero, its limit and window from one.'));

        new Decision(false, $limit, $remaining, $reset, $wait, $window);
    }

    public function testTheSmallestValuesAreAccepted(): void
    {
        self::assertSame(1, new Decision(true, 1, 0, 0, 0, 1)->window);
    }

    public function testTheHeaderStylesSayWhatTheyWrite(): void
    {
        self::assertSame([true, false], [HeaderStyle::Draft->draft(), HeaderStyle::Draft->legacy()]);
        self::assertSame([false, true], [HeaderStyle::Legacy->draft(), HeaderStyle::Legacy->legacy()]);
        self::assertSame([true, true], [HeaderStyle::Both->draft(), HeaderStyle::Both->legacy()]);
        self::assertSame([false, false], [HeaderStyle::None->draft(), HeaderStyle::None->legacy()]);
    }
}
