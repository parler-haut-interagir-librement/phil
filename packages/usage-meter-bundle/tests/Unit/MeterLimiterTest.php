<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Unit;

use Override;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeter\UsageMeter;
use Phil\UsageMeterBundle\Infrastructure\RateLimit\MeterLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(MeterLimiter::class)]
final class MeterLimiterTest extends TestCase
{
    private MockClock $clock;

    private UsageMeter $meter;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-03-14 10:30:15', 'UTC');
        $this->meter = new UsageMeter(
            new Meters(
                new Meter('requests', RollingWindow::minutes(1), Limit::of(3)),
                new Meter('reads', CalendarPeriod::day(), Limit::unlimited()),
                new Meter('actions', CalendarPeriod::day(), Limit::of(100)),
            ),
            new InMemoryUsageStore(),
            $this->clock,
        );
    }

    public function testEachRequestCountsOnTheMeterUntilItRefuses(): void
    {
        $limiter = $this->limiter('api', 'requests');

        foreach ([2, 1, 0] as $remaining) {
            self::assertEquals(Decision::accepted(3, $remaining, 45), $limiter->consume('alice', 1, $this->request()));
        }

        self::assertEquals(Decision::refused(3, 0, 45, 45), $limiter->consume('alice', 1, $this->request()));
        self::assertSame(3, $this->meter->usage('alice', 'requests')->used);
        self::assertSame(1, $limiter->consume('bob', 2, $this->request())->remaining);
    }

    public function testAMeterWithoutALimitWritesNoHeader(): void
    {
        self::assertEquals(Decision::accepted(null, 0, 48585), $this->limiter('read', 'reads')->consume('alice', 1, $this->request()));
    }

    public function testTheResetIsNeverBelowZero(): void
    {
        $late = new MeterLimiter($this->meter, new MockClock('2026-03-14 10:32:00', 'UTC'), 'api', 'requests');

        self::assertSame(0, $late->consume('carol', 1, $this->request())->resetAfter);
        self::assertSame(1, new MeterLimiter($this->meter, new MockClock('2026-03-14 10:30:59', 'UTC'), 'api', 'requests')->consume('carol', 1, $this->request())->resetAfter);
    }

    public function testARetriedRequestWithItsIdempotencyKeyCountsOnce(): void
    {
        $keyed = $this->limiter('keyed', 'actions', true);
        $plain = $this->limiter('plain', 'actions');

        self::assertSame(99, $keyed->consume('alice', 1, $this->request('POST', 'abc-123'))->remaining);
        self::assertSame(99, $keyed->consume('alice', 1, $this->request('POST', '"abc-123"'))->remaining, 'A key and the same key quoted are one.');
        self::assertSame(98, $plain->consume('alice', 1, $this->request('POST', 'abc-123'))->remaining, 'A limiter without idempotency_key counts it.');

        foreach ([
            'another key' => $this->request('POST', 'abc-124'),
            'another method' => $this->request('PUT', 'abc-123'),
            'another URI' => $this->request('POST', 'abc-123', '/?page=2'),
            'another body' => $this->request('POST', 'abc-123', '/', 'other'),
        ] as $case => $other) {
            $before = $this->meter->usage('alice', 'actions')->used;
            $keyed->consume('alice', 1, $other);
            self::assertSame($before + 1, $this->meter->usage('alice', 'actions')->used, $case);
        }

        $this->clock->modify('+1 day');
        $keyed->consume('alice', 1, $this->request('GET', 'free'));
        $keyed->consume('alice', 1, $this->request('GET', 'free'));
        self::assertSame(2, $this->meter->usage('alice', 'actions')->used, 'A safe method is counted every time.');

        $keyed->consume('alice', 1, $this->request('DELETE'));
        $keyed->consume('alice', 1, $this->request('DELETE'));
        self::assertSame(4, $this->meter->usage('alice', 'actions')->used, 'Without the header, every request counts.');
    }

    public function testAMeterLimiterIgnoresTheHeaderByDefault(): void
    {
        $plain = new MeterLimiter($this->meter, $this->clock, 'plain', 'actions');
        $plain->consume('alice', 1, $this->request('POST', 'k'));
        $plain->consume('alice', 1, $this->request('POST', 'k'));

        self::assertSame(2, $this->meter->usage('alice', 'actions')->used);
    }

    public function testTheIdempotencyKeyIsBoundToTheLimiterAndTheSecret(): void
    {
        $this->limiter('keyed', 'actions', true)->consume('alice', 1, $this->request('POST', 'k'));
        $this->limiter('keyed-too', 'actions', true)->consume('alice', 1, $this->request('POST', 'k'));
        self::assertSame(2, $this->meter->usage('alice', 'actions')->used, 'Two limiters of one meter count the request each.');

        new MeterLimiter($this->meter, $this->clock, 'keyed', 'actions', true, 'other secret')->consume('alice', 1, $this->request('POST', 'k'));
        self::assertSame(3, $this->meter->usage('alice', 'actions')->used);
        self::assertTrue($this->meter->record('alice', 'actions', 1, hash_hmac('sha256', implode("\n", ['keyed', 'POST', '/', hash('sha256', ''), 'k']), 'secret'))->replayed, 'The store keeps the HMAC of the limiter, the method, the URI, the body and the header.');
        self::assertSame('Idempotency-Key', MeterLimiter::IDEMPOTENCY_HEADER);
    }

    public function testAMalformedIdempotencyKeyIsABadRequest(): void
    {
        $keyed = $this->limiter('keyed', 'actions', true);

        foreach (['', '""', '"', str_repeat('k', 256), 'caf'."\u{e9}", "a\tb", 'a"b', '"a\\"b"', 'a\\b', '"abc"x', 'x"abc"', "\"abc\"\n", "abc\n", "\"a\nb\""] as $value) {
            try {
                $keyed->consume('alice', 1, $this->request('POST', $value));
                self::fail(var_export($value, true));
            } catch (BadRequestHttpException $badRequestHttpException) {
                self::assertSame('The Idempotency-Key header is 1 to 255 printable ASCII characters, without quotes or backslashes inside.', $badRequestHttpException->getMessage());
            }
        }

        $keyed->consume('alice', 1, $this->request('POST', str_repeat('k', 255)));
        $keyed->consume('alice', 1, $this->request('POST', '"'.str_repeat('k', 255).'"'));
        $keyed->consume('alice', 1, $this->request('POST', ' ~!#[]'));
        $keyed->consume('alice', 1, $this->request('GET', ''));
        $this->limiter('plain', 'actions')->consume('alice', 1, $this->request('POST', ''));
        self::assertSame(4, $this->meter->usage('alice', 'actions')->used, 'A safe method and a limiter without idempotency_key ignore the header.');
    }

    private function limiter(string $name, string $meter, bool $idempotencyKey = false): MeterLimiter
    {
        return new MeterLimiter($this->meter, $this->clock, $name, $meter, $idempotencyKey, 'secret');
    }

    private function request(string $method = 'GET', ?string $key = null, string $uri = '/', string $body = ''): Request
    {
        $request = Request::create($uri, $method, content: $body);

        if (null !== $key) {
            $request->headers->set(MeterLimiter::IDEMPOTENCY_HEADER, $key);
        }

        return $request;
    }
}
