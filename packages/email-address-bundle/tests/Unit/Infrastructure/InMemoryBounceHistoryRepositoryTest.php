<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(InMemoryBounceHistoryRepository::class)]
final class InMemoryBounceHistoryRepositoryTest extends TestCase
{
    public function testAClaimLastsUntilItsEnd(): void
    {
        $clock = new MockClock('2026-11-20 10:00:00');
        $history = new InMemoryBounceHistoryRepository($clock);
        $until = $clock->now()->modify('+1 hour');

        self::assertTrue($history->claim('notice', $until));
        self::assertFalse($history->claim('notice', $until));
        $clock->modify('+59 minutes 59 seconds');
        self::assertFalse($history->claim('notice', $until));
        $clock->modify('+1 second');
        self::assertTrue($history->claim('notice', $until));
    }

    public function testTheSoftBouncesWithinTheWindowAreCountedAndForgotten(): void
    {
        $clock = new MockClock('2026-11-20 10:00:00');
        $history = new InMemoryBounceHistoryRepository($clock);
        $now = $clock->now();

        self::assertSame(1, $history->recordSoftBounce('ada', $now->modify('-3 days'), $now->modify('-30 days')));
        self::assertSame(2, $history->recordSoftBounce('ada', $now, $now->modify('-3 days')));
        self::assertSame(2, $history->recordSoftBounce('ada', $now, $now->modify('-2 days')));
        self::assertSame(1, $history->recordSoftBounce('grace', $now, $now->modify('-2 days')));

        $history->forgetSoftBounces('ada');
        self::assertSame(1, $history->recordSoftBounce('ada', $now, $now->modify('-2 days')));
        self::assertSame(2, $history->recordSoftBounce('grace', $now, $now->modify('-2 days')));
    }

    public function testAResetForgetsEverything(): void
    {
        $history = new InMemoryBounceHistoryRepository();
        $until = new DateTimeImmutable('+1 hour');
        $history->claim('notice', $until);
        $history->recordSoftBounce('ada', new DateTimeImmutable(), new DateTimeImmutable('-1 day'));

        $history->reset();

        self::assertTrue($history->claim('notice', $until));
        self::assertSame(1, $history->recordSoftBounce('ada', new DateTimeImmutable(), new DateTimeImmutable('-1 day')));
    }
}
