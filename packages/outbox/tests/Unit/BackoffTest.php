<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use DateTimeImmutable;
use Phil\Outbox\Backoff;
use Phil\Outbox\Exception\InvalidSetting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Backoff::class)]
#[CoversClass(InvalidSetting::class)]
final class BackoffTest extends TestCase
{
    public function testTheDelayDoublesUpToTheLongest(): void
    {
        $backoff = new Backoff(10, 70, 5);

        self::assertSame(10, $backoff->delay(0));
        self::assertSame(10, $backoff->delay(1));
        self::assertSame(20, $backoff->delay(2));
        self::assertSame(40, $backoff->delay(3));
        self::assertSame(70, $backoff->delay(4));
        self::assertSame(70, $backoff->delay(1000));
        self::assertSame('2026-11-17 10:00:40', $backoff->retryAt(3, new DateTimeImmutable('2026-11-17 10:00:00'))->format('Y-m-d H:i:s'));
    }

    public function testARecordIsDeadAfterItsLastAttempt(): void
    {
        $backoff = new Backoff(maxAttempts: 3);

        self::assertFalse($backoff->exhausted(2));
        self::assertTrue($backoff->exhausted(3));
        self::assertTrue($backoff->exhausted(4));
        self::assertTrue(new Backoff(maxAttempts: 1)->exhausted(1));
    }

    public function testTheDefaults(): void
    {
        $backoff = new Backoff();

        self::assertSame(60, $backoff->firstDelay);
        self::assertSame(86_400, $backoff->longestDelay);
        self::assertSame(10, $backoff->maxAttempts);
        self::assertSame(86_400, $backoff->delay(30));
        self::assertSame(1, new Backoff(1, 1)->delay(9));
        self::assertSame(80, new Backoff(10, 80)->delay(\PHP_INT_MAX), 'The doubling stops at the longest delay, however many the attempts.');
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function refusedSettings(): iterable
    {
        yield 'no first delay' => [0, 10, 1, 'A record waits at least 1 second and the longest delay is not under the first; 0 and 10 are not.'];
        yield 'a longest delay under the first' => [10, 9, 1, 'A record waits at least 1 second and the longest delay is not under the first; 10 and 9 are not.'];
        yield 'no attempt' => [1, 1, 0, 'A record is tried at least once; 0 attempts are not.'];
    }

    #[DataProvider('refusedSettings')]
    public function testASettingOutOfBoundsIsRefused(int $first, int $longest, int $attempts, string $message): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Backoff($first, $longest, $attempts);
    }
}
