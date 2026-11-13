<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Phil\ErrorTracking\Capture;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Scrubber;
use Phil\ErrorTracking\Source;
use Phil\ErrorTracking\Tests\Unit\Fixture\CountingThrottle;
use Phil\ErrorTracking\Tests\Unit\Fixture\Events;
use Phil\ErrorTracking\Tests\Unit\Fixture\FrozenClock;
use Phil\ErrorTracking\Tests\Unit\Fixture\MemoryStore;
use Phil\ErrorTracking\Tests\Unit\Fixture\RecordingAlerter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ErrorTracker::class)]
#[CoversClass(Capture::class)]
final class ErrorTrackerTest extends TestCase
{
    private MemoryStore $store;

    private RecordingAlerter $alerter;

    protected function setUp(): void
    {
        $this->store = new MemoryStore();
        $this->alerter = new RecordingAlerter();
    }

    public function testTheFirstEventOpensAGroupAndAlerts(): void
    {
        $capture = $this->tracker()->capture(Events::one('No user jane@example.com'));

        self::assertInstanceOf(Capture::class, $capture);
        self::assertSame(str_repeat('a', 32), $capture->eventId);
        self::assertSame(Occurrence::First, $capture->occurrence);
        self::assertSame(GroupStatus::Open, $capture->status);
        $group = $this->store->groups[$capture->fingerprint];
        self::assertSame('RuntimeException: No user [email]', $group->title);
        self::assertSame('RuntimeException: No user [email]', $this->store->events[$capture->fingerprint][0]->title());
        self::assertSame(new Fingerprinter()->fingerprint(new Scrubber()->scrub(Events::one('No user jane@example.com'))), $capture->fingerprint);
        self::assertSame([[$group, Occurrence::First]], $this->alerter->alerts);
    }

    public function testARepeatIsCountedWithoutAlert(): void
    {
        $tracker = $this->tracker();
        $tracker->capture(Events::one());

        $capture = $tracker->capture(Events::one('User 43 not found'));

        self::assertSame(Occurrence::Repeat, $capture?->occurrence);
        self::assertSame(2, $this->store->groups[$capture->fingerprint]->timesSeen);
        self::assertCount(2, $this->store->events[$capture->fingerprint]);
        self::assertCount(1, $this->alerter->alerts);
    }

    public function testARegressionAlerts(): void
    {
        $tracker = $this->tracker();
        $fingerprint = $tracker->capture(Events::one())->fingerprint ?? '';
        $this->store->groups[$fingerprint] = $this->store->groups[$fingerprint]->resolve();

        self::assertSame(Occurrence::Repeat, $tracker->capture(Events::one())?->occurrence);
        self::assertSame(GroupStatus::Resolved, $this->store->groups[$fingerprint]->status);

        $capture = $tracker->capture(Events::one(release: '1.1.0'));
        self::assertSame(Occurrence::Regression, $capture?->occurrence);
        self::assertSame(GroupStatus::Regressed, $capture->status);
        self::assertSame(Occurrence::Regression, $this->alerter->alerts[1][1]);
        self::assertSame(GroupStatus::Regressed, $this->alerter->alerts[1][0]->status);
        self::assertSame(Occurrence::Repeat, $tracker->capture(Events::one(release: '1.2.0'))?->occurrence);
        self::assertCount(2, $this->alerter->alerts);
    }

    public function testAnIgnoredGroupNeverAlerts(): void
    {
        $tracker = $this->tracker();
        $fingerprint = $tracker->capture(Events::one())->fingerprint ?? '';
        $this->store->groups[$fingerprint] = $this->store->groups[$fingerprint]->ignore();

        self::assertSame(GroupStatus::Ignored, $tracker->capture(Events::one(release: '9.0.0'))?->status);
        self::assertCount(1, $this->alerter->alerts);
    }

    public function testTheThrottleBoundsTheEventsOfAGroup(): void
    {
        $throttle = new CountingThrottle(2);
        $tracker = $this->tracker(throttle: $throttle);

        self::assertInstanceOf(Capture::class, $tracker->capture(Events::one()));
        self::assertInstanceOf(Capture::class, $tracker->capture(Events::one()));
        self::assertNull($tracker->capture(Events::one()));
        $fingerprint = (string) array_key_first($throttle->asked);
        self::assertSame(2, $this->store->groups[$fingerprint]->timesSeen);
        self::assertSame(3, $throttle->asked[$fingerprint]);
    }

    public function testTheSamplerLeavesEventsOut(): void
    {
        $tracker = $this->tracker(new Sampler(0.0));

        self::assertNull($tracker->capture(Events::one()));
        self::assertNull($tracker->captureThrowable(new RuntimeException('x')));
        self::assertSame([], $this->store->groups);
    }

    public function testAThrowableIsCaptured(): void
    {
        $capture = $this->tracker()->captureThrowable(new RuntimeException('Export of order 12 failed'), new CaptureContext(Source::Messenger));
        $event = $this->store->events[$capture->fingerprint ?? ''][0];

        self::assertSame('RuntimeException: Export of order 12 failed', $event->title());
        self::assertSame(Source::Messenger, $event->source);
        self::assertSame(Source::Manual, $this->store->events[$this->tracker()->captureThrowable(new RuntimeException('Other'))->fingerprint ?? ''][0]->source);
    }

    public function testTheDefaults(): void
    {
        $tracker = new ErrorTracker(new EventFactory('/app', new FrozenClock()), $this->store);
        $capture = $tracker->capture(Events::one(context: ['token' => 'abc']));

        self::assertSame(['token' => '[Filtered]'], $this->store->events[$capture->fingerprint ?? ''][0]->context);
        self::assertSame(Occurrence::First, $capture?->occurrence);
    }

    private function tracker(?Sampler $sampler = null, ?CountingThrottle $throttle = null): ErrorTracker
    {
        return new ErrorTracker(new EventFactory('/app', new FrozenClock()), $this->store, new Scrubber(), new Fingerprinter(), $sampler ?? new Sampler(), $throttle, $this->alerter);
    }
}
