<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Period;

use DateTimeImmutable;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeter\Period\Window;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RollingWindow::class)]
#[CoversClass(Window::class)]
final class RollingWindowTest extends TestCase
{
    public function testTheWindowRunsFromTheOldestBucketToTheEndOfTheCurrentOne(): void
    {
        $window = RollingWindow::hours(24)->windowAt(new DateTimeImmutable('2026-03-14T10:30:00+00:00'));

        self::assertSame('2026-03-13T11:00:00+00:00', $window->start->format(\DATE_ATOM));
        self::assertSame('2026-03-14T11:00:00+00:00', $window->end->format(\DATE_ATOM));
        self::assertSame('2026-03-14T10:00:00+00:00', $window->counterStart->format(\DATE_ATOM));
        self::assertSame('2026-03-15T10:00:00+00:00', $window->counterEnd->format(\DATE_ATOM));
    }

    public function testABucketStartsOnItsBoundary(): void
    {
        $window = RollingWindow::minutes(10)->windowAt(new DateTimeImmutable('2026-03-14T10:30:00+00:00'));

        self::assertSame('2026-03-14T10:30:00+00:00', $window->counterStart->format(\DATE_ATOM));
        self::assertSame('2026-03-14T10:31:00+00:00', $window->end->format(\DATE_ATOM));
        self::assertSame('2026-03-14T10:21:00+00:00', $window->start->format(\DATE_ATOM));
    }

    public function testOneBucketMakesFixedBlocks(): void
    {
        $window = new RollingWindow(3600)->windowAt(new DateTimeImmutable('2026-03-14T10:59:59+00:00'));

        self::assertSame('2026-03-14T10:00:00+00:00', $window->start->format(\DATE_ATOM));
        self::assertSame('2026-03-14T10:00:00+00:00', $window->counterStart->format(\DATE_ATOM));
        self::assertSame('2026-03-14T11:00:00+00:00', $window->end->format(\DATE_ATOM));
        self::assertSame('2026-03-14T11:00:00+00:00', $window->counterEnd->format(\DATE_ATOM));
    }

    public function testBucketsAreAlignedOnTheEpochWhateverTheZone(): void
    {
        $window = RollingWindow::days(30)->windowAt(new DateTimeImmutable('2026-03-14T22:00:00-05:00'));

        self::assertSame('2026-03-15T00:00:00+00:00', $window->counterStart->format(\DATE_ATOM));
        self::assertSame('2026-02-14T00:00:00+00:00', $window->start->format(\DATE_ATOM));
    }

    public function testMomentsBeforeTheEpochFallInTheirBucket(): void
    {
        $window = new RollingWindow(60)->windowAt(new DateTimeImmutable('@-30'));

        self::assertSame(-60, $window->counterStart->getTimestamp());
        self::assertSame(0, $window->end->getTimestamp());
    }

    public function testTheLengthsAreKept(): void
    {
        $window = RollingWindow::hours(24);

        self::assertSame(86400, $window->seconds);
        self::assertSame(24, $window->buckets);
        self::assertSame(3600, $window->bucketSeconds);
        self::assertSame([600, 10, 60], [RollingWindow::minutes(10)->seconds, RollingWindow::minutes(10)->buckets, RollingWindow::minutes(10)->bucketSeconds]);
        self::assertSame([2592000, 30, 86400], [RollingWindow::days(30)->seconds, RollingWindow::days(30)->buckets, RollingWindow::days(30)->bucketSeconds]);
        self::assertSame(1, new RollingWindow(5)->buckets);
        self::assertSame(1, new RollingWindow(1)->bucketSeconds);
        self::assertSame(1, new RollingWindow(1440, 1440)->bucketSeconds);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidWindows(): iterable
    {
        yield 'no length' => [0, 1];
        yield 'no bucket' => [60, 0];
        yield 'too many buckets' => [1441, 1441];
        yield 'buckets that do not divide' => [100, 7];
    }

    #[DataProvider('invalidWindows')]
    public function testTheBucketsDivideTheWindow(int $seconds, int $buckets): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A rolling window lasts one second or more and is cut into 1 to 1440 buckets that divide it exactly; got %d seconds in %d buckets.', $seconds, $buckets));

        new RollingWindow($seconds, $buckets);
    }
}
