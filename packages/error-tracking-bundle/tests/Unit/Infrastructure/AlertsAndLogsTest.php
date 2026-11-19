<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Infrastructure\Monolog\ErrorTrackingHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingChatter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
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
