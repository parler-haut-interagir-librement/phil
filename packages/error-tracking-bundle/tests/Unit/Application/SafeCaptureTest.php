<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use DomainException;
use LogicException;
use Phil\ErrorTracking\Capture;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;

#[CoversClass(SafeCapture::class)]
final class SafeCaptureTest extends TestCase
{
    public function testAThrowableIsCapturedOnce(): void
    {
        $tracking = new Tracking();
        $throwable = new RuntimeException('Export failed');

        self::assertInstanceOf(Capture::class, $tracking->capture->throwable($throwable, new CaptureContext(Source::Http)));
        self::assertNull($tracking->capture->throwable($throwable, new CaptureContext(Source::Log)));
        self::assertSame(Source::Http, $tracking->store->onlyEvent()->source);

        $tracking->capture->reset();
        self::assertSame(Occurrence::Repeat, $tracking->capture->throwable($throwable, new CaptureContext())?->occurrence);
    }

    public function testIgnoredExceptionsAndTheirSubclasses(): void
    {
        $tracking = new Tracking([LogicException::class]);

        self::assertTrue($tracking->capture->isIgnored(new DomainException()));
        self::assertTrue($tracking->capture->isIgnored(new LogicException()));
        self::assertFalse($tracking->capture->isIgnored(new RuntimeException()));
        self::assertNull($tracking->capture->throwable(new DomainException('d'), new CaptureContext()));
        self::assertSame([], $tracking->store->groups);
        self::assertFalse(new Tracking()->capture->isIgnored(new LogicException()));
    }

    public function testAnEventIsCaptured(): void
    {
        $tracking = new Tracking();
        $event = $tracking->factory->fromMessage('Log record', 'Order {id} failed', []);

        $first = $tracking->capture->event($event);
        $second = $tracking->capture->event($event);

        self::assertSame(Occurrence::First, $first?->occurrence);
        self::assertSame(Occurrence::Repeat, $second?->occurrence);
    }

    public function testAFailedCaptureIsLoggedOnceAndNeverThrows(): void
    {
        $logger = new RecordingLogger();
        $tracking = new Tracking(logger: $logger);
        $tracking->store->failing = true;

        $nested = [];
        $logger->whileLogging = static function () use ($tracking, &$nested): void {
            $nested[] = $tracking->capture->throwable(new RuntimeException('From the log handler'), new CaptureContext());
            $nested[] = $tracking->capture->event($tracking->factory->fromMessage('Log record', 'nested', []));
        };

        self::assertNull($tracking->capture->throwable(new RuntimeException('first'), new CaptureContext()));
        self::assertSame([[LogLevel::WARNING, 'An error could not be captured: {failure}.', ['failure' => RuntimeException::class]]], $logger->records);
        self::assertSame([null, null], $nested);

        self::assertNull($tracking->capture->event($tracking->factory->fromMessage('Log record', 'again', [])));
        self::assertSame(2, $logger->count());

        $tracking->store->failing = false;
        self::assertInstanceOf(Capture::class, $tracking->capture->throwable(new RuntimeException('after'), new CaptureContext()));
    }

    public function testAFailedCaptureWithoutLogger(): void
    {
        $tracking = new Tracking();
        $tracking->store->failing = true;

        self::assertNull($tracking->capture->throwable(new RuntimeException('x'), new CaptureContext()));
        $tracking->store->failing = false;
        self::assertInstanceOf(Capture::class, $tracking->capture->event($tracking->factory->fromMessage('E', 'm', [])));
    }
}
