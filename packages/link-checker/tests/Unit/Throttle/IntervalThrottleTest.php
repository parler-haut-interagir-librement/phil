<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Throttle;

use Phil\LinkChecker\Exception\InvalidOption;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IntervalThrottle::class)]
#[CoversClass(InvalidOption::class)]
final class IntervalThrottleTest extends TestCase
{
    private function elapsed(MockClock $clock, MockClock $start): float
    {
        return (float) $clock->now()->format('U.u') - (float) $start->now()->format('U.u');
    }

    public function testTwoRequestsToTheSameHostAreAnIntervalApart(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(2.0, $clock);

        $throttle->wait('example.com');
        self::assertSame(0.0, $this->elapsed($clock, $start));

        $throttle->wait('example.com');
        self::assertSame(2.0, $this->elapsed($clock, $start));

        $throttle->wait('example.com');
        self::assertSame(4.0, $this->elapsed($clock, $start));
    }

    public function testDifferentHostsDoNotWaitForEachOther(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(1.5, $clock);

        $throttle->wait('a.example');
        $throttle->wait('b.example');
        self::assertSame(0.0, $this->elapsed($clock, $start));

        $clock->sleep(0.5);
        $throttle->wait('a.example');
        self::assertSame(1.5, $this->elapsed($clock, $start));
    }

    public function testTimeThatPassedCountsTowardsTheInterval(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(1.0, $clock);

        $throttle->wait('example.com');

        $clock->sleep(3.0);
        $throttle->wait('example.com');
        self::assertSame(3.0, $this->elapsed($clock, $start));

        $throttle->wait('example.com');
        self::assertSame(4.0, $this->elapsed($clock, $start));
    }

    public function testTheLeastRecentHostIsForgottenPastTheLimit(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(10.0, $clock, 2);

        $throttle->wait('a.example');
        $throttle->wait('b.example');
        // a.example becomes the most recent one again, after its interval.
        $throttle->wait('a.example');
        self::assertSame(10.0, $this->elapsed($clock, $start));

        $throttle->wait('c.example');
        // b.example was forgotten: it does not wait.
        $throttle->wait('b.example');
        self::assertSame(10.0, $this->elapsed($clock, $start));

        // b.example pushed a.example out; c.example is remembered still: it waits its interval.
        $throttle->wait('c.example');
        self::assertSame(20.0, $this->elapsed($clock, $start));
        $throttle->wait('a.example');
        self::assertSame(20.0, $this->elapsed($clock, $start));
    }

    public function testTwoHostsAreRememberedAtTheLimitOfTwo(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(10.0, $clock, 2);

        $throttle->wait('a.example');
        $throttle->wait('b.example');
        $throttle->wait('a.example');
        self::assertSame(10.0, $this->elapsed($clock, $start));
    }

    public function testOneHostCanBeRemembered(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(3.0, $clock, 1);

        $throttle->wait('a.example');
        $throttle->wait('a.example');
        self::assertSame(3.0, $this->elapsed($clock, $start));

        $throttle->wait('b.example');
        $throttle->wait('a.example');
        self::assertSame(3.0, $this->elapsed($clock, $start));
    }

    public function testResetForgetsEveryHost(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(5.0, $clock);

        $throttle->wait('example.com');
        $throttle->reset();
        $throttle->wait('example.com');
        self::assertSame(0.0, $this->elapsed($clock, $start));
    }

    public function testNoIntervalNeverWaits(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00');
        $start = new MockClock('2026-11-21 10:00:00');
        $throttle = new IntervalThrottle(0.0, $clock, IntervalThrottle::MAX_HOSTS);

        $throttle->wait('example.com');
        $throttle->wait('example.com');
        self::assertSame(0.0, $this->elapsed($clock, $start));

        $longest = new IntervalThrottle(IntervalThrottle::MAX_INTERVAL, $clock);
        $longest->wait('example.com');
        $longest->wait('example.com');
        self::assertSame(60.0, $this->elapsed($clock, $start));
    }

    public function testTheDefaults(): void
    {
        self::assertSame(1.0, IntervalThrottle::DEFAULT_INTERVAL);
        self::assertSame(1_000, IntervalThrottle::DEFAULT_HOSTS);

        // Past a thousand hosts, the first one is forgotten and the second one is not.
        foreach (['host-0.example' => 0.0, 'host-1.example' => 1.0] as $host => $waited) {
            $clock = new MockClock('2026-11-21 10:00:00');
            $start = new MockClock('2026-11-21 10:00:00');
            $throttle = new IntervalThrottle(clock: $clock);

            foreach (range(0, 1_000) as $index) {
                $throttle->wait('host-'.$index.'.example');
            }

            $throttle->wait($host);
            self::assertSame($waited, $this->elapsed($clock, $start), $host);
        }
    }

    public function testTheDefaultIntervalIsOneSecond(): void
    {
        $throttle = new IntervalThrottle();
        $before = microtime(true);
        $throttle->wait('example.com');
        self::assertLessThan(0.5, microtime(true) - $before);
    }

    /**
     * @return iterable<string, array{float, int, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'a negative interval' => [-0.1, 10, 'The interval between two requests to a host is -0.1; it must lie between 0 and 60.'];
        yield 'too long an interval' => [60.1, 10, 'The interval between two requests to a host is 60.1; it must lie between 0 and 60.'];
        yield 'no host' => [1.0, 0, 'The hosts remembered is 0; it must lie between 1 and 100000.'];
        yield 'too many hosts' => [1.0, 100_001, 'The hosts remembered is 100001; it must lie between 1 and 100000.'];
    }

    #[DataProvider('invalidOptions')]
    public function testOptionsOutOfTheirBoundsAreRefused(float $interval, int $hosts, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new IntervalThrottle($interval, new MockClock(), $hosts);
    }
}
