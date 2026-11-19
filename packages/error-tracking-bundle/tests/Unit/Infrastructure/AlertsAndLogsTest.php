<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Monolog\Level;
use Monolog\LogRecord;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Infrastructure\Monolog\ErrorTrackingHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingChatter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ChatterAlerter::class)]
#[CoversClass(ErrorTrackingHandler::class)]
final class AlertsAndLogsTest extends TestCase
{
    public function testAFirstEventIsAnnounced(): void
    {
        $chatter = new RecordingChatter();
        new ChatterAlerter($chatter, 'discord', 'prod')->alert($this->group(), Occurrence::First);

        self::assertCount(1, $chatter->messages);
        self::assertSame("[prod] New error: RuntimeException: Export failed\nIn App\\Export::run (src/Export.php:3)\nSeen 1 time(s) on 1.2.0 — group 0123456789ab", $chatter->messages[0]->getSubject());
        self::assertSame('discord', $chatter->messages[0]->getTransport());
    }

    public function testARegressionWithoutEnvironmentNorCulprit(): void
    {
        $chatter = new RecordingChatter();
        new ChatterAlerter($chatter)->alert(new ErrorGroup(str_repeat('a', 64), 'E: '.str_repeat('é', 2000), null, GroupStatus::Regressed, 4, new DateTimeImmutable(), new DateTimeImmutable()), Occurrence::Regression);

        $subject = $chatter->messages[0]->getSubject();
        self::assertStringStartsWith('Regression: E: éé', $subject);
        self::assertSame(1800, mb_strlen($subject));
        self::assertNull($chatter->messages[0]->getTransport());

        new ChatterAlerter($chatter)->alert(new ErrorGroup(str_repeat('a', 64), 'E: m', null, GroupStatus::Regressed, 4, new DateTimeImmutable(), new DateTimeImmutable()), Occurrence::Regression);
        self::assertSame("Regression: E: m\nSeen 4 time(s) — group aaaaaaaaaaaa", $chatter->messages[1]->getSubject());
    }

    public function testARecordWithAnExceptionIsCapturedOnce(): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory);
        $exception = new RuntimeException('Payment failed');
        $handler->handle($this->record(Level::Critical, 'Uncaught', ['exception' => $exception]));
        $handler->handle($this->record(Level::Error, 'Again', ['exception' => $exception]));

        $event = $tracking->store->onlyEvent();
        self::assertSame(Source::Log, $event->source);
        self::assertSame(Severity::Fatal, $event->severity);
        self::assertSame('RuntimeException: Payment failed', $event->title());
        self::assertSame(['channel' => 'app'], $event->context);
    }

    public function testARecordWithoutExceptionKeepsItsPlaceholders(): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory);
        $handler->handle($this->record(Level::Error, 'Order {id} failed', ['id' => 12, 'exception' => 'not a throwable']));

        $event = $tracking->store->onlyEvent();
        self::assertSame('Log record: Order {id} failed', $event->title());
        self::assertSame(Severity::Error, $event->severity);
        self::assertSame([], $event->exceptions[0]->frames);
        self::assertSame(['channel' => 'app'], $event->context);
    }

    #[TestWith([Level::Warning, Severity::Warning, true])]
    #[TestWith([Level::Error, Severity::Error, true])]
    #[TestWith([Level::Critical, Severity::Fatal, true])]
    #[TestWith([Level::Emergency, Severity::Fatal, true])]
    #[TestWith([Level::Notice, null, false])]
    public function testTheLevels(Level $level, ?Severity $severity, bool $captured): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory, Level::Warning);
        $handler->handle($this->record($level, 'm'));

        self::assertSame($captured, [] !== $tracking->store->events);

        if ($severity instanceof Severity) {
            self::assertSame($severity, $tracking->store->onlyEvent()->severity);
        }
    }

    public function testAnExceptionARedactingProcessorTurnedIntoAnArrayKeepsItsClassAndPlace(): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory);
        $exception = [
            'class' => RuntimeException::class,
            'message' => 'Export of /reset?[redacted] failed',
            'code' => 0,
            'file' => '/srv/project-under-test/src/Export.php',
            'line' => 12,
            'previous' => ['class' => LogicException::class, 'message' => 'Inner', 'code' => 0, 'file' => '/srv/project-under-test/vendor/lib/Client.php', 'line' => 40, 'previous' => null],
        ];
        $handler->handle($this->record(Level::Critical, 'Uncaught', ['exception' => $exception]));

        $event = $tracking->store->onlyEvent();
        self::assertSame('RuntimeException: Export of /reset?[redacted] failed', $event->title());
        self::assertSame(Severity::Fatal, $event->severity);
        self::assertSame(Source::Log, $event->source);
        self::assertSame(['channel' => 'app'], $event->context);
        self::assertCount(2, $event->exceptions);
        self::assertSame([LogicException::class, 'Inner'], [$event->exceptions[1]->type, $event->exceptions[1]->message]);
        self::assertSame('src/Export.php:12', $event->exceptions[0]->frames[0]->label());
        self::assertTrue($event->exceptions[0]->frames[0]->inApp);
        self::assertSame('vendor/lib/Client.php:40', $event->exceptions[1]->frames[0]->label());
        self::assertSame('src/Export.php:12', $event->culprit()?->label());

        $handler->handle($this->record(Level::Error, 'Elsewhere', ['exception' => ['line' => 13] + $exception]));
        $handler->handle($this->record(Level::Error, 'Other file', ['exception' => ['file' => '/srv/project-under-test/src/Import.php'] + $exception]));
        self::assertCount(2, $tracking->store->groups);
        self::assertSame([2, 1], array_map(\count(...), array_values($tracking->store->events)));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notDescriptions(): iterable
    {
        $exception = ['class' => RuntimeException::class, 'message' => 'm', 'file' => '/srv/a.php', 'line' => 1];

        yield 'without class' => [array_diff_key($exception, ['class' => true])];
        yield 'a class of another type' => [['class' => 1] + $exception];
        yield 'without file' => [array_diff_key($exception, ['file' => true])];
        yield 'a file of another type' => [['file' => 1] + $exception];
        yield 'without line' => [array_diff_key($exception, ['line' => true])];
        yield 'a line of another type' => [['line' => '1'] + $exception];
    }

    #[DataProvider('notDescriptions')]
    public function testAnArrayThatDescribesNoExceptionLeavesTheMessage(mixed $exception): void
    {
        $tracking = new Tracking();
        new ErrorTrackingHandler($tracking->capture, $tracking->factory)->handle($this->record(Level::Error, 'Order {id} failed', ['exception' => $exception]));

        self::assertSame('Log record: Order {id} failed', $tracking->store->onlyEvent()->title());
    }

    public function testTheChainOfAnArrayIsReadAsFarAsItDescribesExceptionsAndBounded(): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory);
        $handler->handle($this->record(Level::Error, 'm', ['exception' => ['class' => 'A', 'message' => ['not text'], 'file' => '/a.php', 'line' => 1, 'previous' => ['class' => 'B', 'line' => 2]]]));

        $event = $tracking->store->onlyEvent();
        self::assertCount(1, $event->exceptions);
        self::assertSame('A', $event->title());
        self::assertSame('/a.php:1', $event->exceptions[0]->frames[0]->label());

        $deep = null;

        for ($index = 0; $index < 15; ++$index) {
            $deep = ['class' => 'E'.$index, 'file' => '/b.php', 'line' => $index, 'previous' => $deep];
        }

        $handler->handle($this->record(Level::Error, 'm', ['exception' => $deep]));
        $events = array_merge(...array_values($tracking->store->events));
        $chain = array_last($events);
        self::assertInstanceOf(ErrorEvent::class, $chain);
        self::assertCount(EventFactory::DEFAULT_MAX_CHAIN, $chain->exceptions);
        self::assertSame(['E14', 'E5'], [$chain->exceptions[0]->type, $chain->exceptions[9]->type]);
    }

    public function testAnArrayOfAnIgnoredClassIsLeftOut(): void
    {
        $tracking = new Tracking([LogicException::class]);
        new ErrorTrackingHandler($tracking->capture, $tracking->factory)->handle($this->record(Level::Error, 'm', ['exception' => ['class' => LogicException::class, 'file' => '/a.php', 'line' => 1]]));

        self::assertSame([], $tracking->store->events);
    }

    public function testTheDefaultLevelIsError(): void
    {
        $tracking = new Tracking();
        $handler = new ErrorTrackingHandler($tracking->capture, $tracking->factory);

        self::assertFalse($handler->isHandling($this->record(Level::Warning, 'm')));
        self::assertTrue($handler->isHandling($this->record(Level::Error, 'm')));
    }

    /**
     * @param array<array-key, mixed> $context
     */
    private function record(Level $level, string $message, array $context = []): LogRecord
    {
        return new LogRecord(new DateTimeImmutable(), 'app', $level, $message, $context);
    }

    private function group(): ErrorGroup
    {
        return new ErrorGroup('0123456789ab'.str_repeat('f', 52), 'RuntimeException: Export failed', 'App\Export::run (src/Export.php:3)', GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable(), '1.2.0');
    }
}
