<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use DomainException;
use Exception;
use LogicException;
use Phil\ErrorTracking\Capture;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionProperty;
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

    public function testAnExceptionKnownByItsPlaceIsLeftOutOnceAfterItsThrowable(): void
    {
        $tracking = new Tracking([LogicException::class]);
        $throwable = new RuntimeException('Export failed');
        $event = static fn (): ErrorEvent => $tracking->factory->fromMessage(RuntimeException::class, 'Export failed', []);
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        $tracking->capture->throwable($throwable, new CaptureContext(Source::Http));
        $captured = [];

        foreach ([[RuntimeException::class, $line + 1], [LogicException::class.'X', $line], [RuntimeException::class, $line], [RuntimeException::class, $line], [RuntimeException::class, $line]] as [$class, $at]) {
            $captured[] = $tracking->capture->exception($class, $file, $at, $event) instanceof Capture;
        }

        self::assertSame([true, true, false, true, true], $captured);
        self::assertCount(5, array_merge(...array_values($tracking->store->events)));
        self::assertNull($tracking->capture->exception(DomainException::class, '/srv/b.php', 1, $event));
        self::assertTrue($tracking->capture->isIgnored(DomainException::class));
        self::assertFalse($tracking->capture->isIgnored(RuntimeException::class));
        self::assertCount(5, array_merge(...array_values($tracking->store->events)));

        $again = new RuntimeException('Again');
        $tracking->capture->throwable($again, new CaptureContext());
        $tracking->capture->reset();
        self::assertInstanceOf(Capture::class, $tracking->capture->exception(RuntimeException::class, $again->getFile(), $again->getLine(), $event));
    }

    public function testTheSignaturesAreBounded(): void
    {
        $tracking = new Tracking();
        $event = static fn (): ErrorEvent => $tracking->factory->fromMessage('E', 'm', []);
        $file = '';

        // Each throwable said to be thrown on a line of its own, so that each leaves a signature of its own.
        for ($line = 1; $line <= SafeCapture::MAX_SIGNATURES + 1; ++$line) {
            $throwable = $this->thrownOnLine($line);
            $file = $throwable->getFile();
            $tracking->capture->throwable($throwable, new CaptureContext());
        }

        self::assertInstanceOf(Capture::class, $tracking->capture->exception(RuntimeException::class, $file, 1, $event));
        self::assertNull($tracking->capture->exception(RuntimeException::class, $file, 2, $event));
        self::assertNull($tracking->capture->exception(RuntimeException::class, $file, SafeCapture::MAX_SIGNATURES + 1, $event));
        self::assertSame(100, SafeCapture::MAX_SIGNATURES);
    }

    public function testAnExceptionKnownByItsPlaceIsRefusedDuringACapture(): void
    {
        $logger = new RecordingLogger();
        $tracking = new Tracking(logger: $logger);
        $tracking->store->failing = true;

        $nested = [];
        $logger->whileLogging = static function () use ($tracking, &$nested): void {
            $nested[] = $tracking->capture->exception('E', '/a.php', 9, static fn (): ErrorEvent => $tracking->factory->fromMessage('E', 'm', []));
        };

        self::assertNull($tracking->capture->exception('E', '/a.php', 1, static fn (): ErrorEvent => $tracking->factory->fromMessage('E', 'm', [])));
        self::assertSame([null], $nested);
        self::assertSame(1, $logger->count());
    }

    private function thrownOnLine(int $line): RuntimeException
    {
        $throwable = new RuntimeException('On line '.$line);
        new ReflectionProperty(Exception::class, 'line')->setValue($throwable, $line);

        return $throwable;
    }
}
