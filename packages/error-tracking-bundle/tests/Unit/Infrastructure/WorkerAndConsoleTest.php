<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Infrastructure\Console\ConsoleErrorListener;
use Phil\ErrorTrackingBundle\Infrastructure\Console\PurgeCommand;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\WorkerFailureListener;
use Phil\ErrorTrackingBundle\Infrastructure\RateLimiter\RateLimiterThrottle;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\MemoryStore;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingAlerter;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\HandlerFailureEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(WorkerFailureListener::class)]
#[CoversClass(MessengerAlerter::class)]
#[CoversClass(AlertErrorGroupHandler::class)]
#[CoversClass(AlertErrorGroup::class)]
#[CoversClass(ConsoleErrorListener::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(RateLimiterThrottle::class)]
final class WorkerAndConsoleTest extends TestCase
{
    public function testAFailedMessageIsCapturedOnceItWillNotBeRetried(): void
    {
        $tracking = new Tracking();
        $listener = new WorkerFailureListener($tracking->capture);
        $retried = new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'async', new RuntimeException('Retried'));
        $retried->setForRetry();

        $listener->onMessageFailed($retried);
        self::assertSame([], $tracking->store->groups);

        $inner = new LogicException('The export failed');
        $listener->onMessageFailed(new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'async', new HandlerFailedException(new Envelope(new stdClass()), [$inner])));
        $event = $tracking->store->onlyEvent();
        self::assertSame(Source::Messenger, $event->source);
        self::assertSame('LogicException: The export failed', $event->title());
        self::assertSame(['message' => stdClass::class, 'transport' => 'async', 'will_retry' => false], $event->context);
    }

    public function testTheHandlerThatFailedTagsTheCaptureAndEachFailureIsCaptured(): void
    {
        $tracking = new Tracking();
        $listener = new WorkerFailureListener($tracking->capture);
        $received = new Envelope(new stdClass(), [new ReceivedStamp('async')]);
        $export = new LogicException('The export failed');
        $mail = new RuntimeException('The mail failed');
        $listener->onHandlerFailed(new HandlerFailureEvent($received, new HandlerDescriptor(static fn (): null => null, ['alias' => 'export']), $export));
        $listener->onHandlerFailed(new HandlerFailureEvent(new Envelope(new stdClass()), new HandlerDescriptor(static fn (): null => null, ['alias' => 'sync']), $mail));

        $listener->onMessageFailed(new WorkerMessageFailedEvent($received, 'async', new HandlerFailedException($received, [$export, $mail])));

        $events = array_merge(...array_values($tracking->store->events));
        self::assertCount(2, $events, 'One capture for each handler that failed.');
        $byTitle = [];

        foreach ($events as $event) {
            $byTitle[$event->title()] = $event->context;
        }

        self::assertSame(['message' => stdClass::class, 'transport' => 'async', 'will_retry' => false, 'handler' => 'Closure@export'], $byTitle['LogicException: The export failed'] ?? null);
        self::assertSame(['message' => stdClass::class, 'transport' => 'async', 'will_retry' => false], $byTitle['RuntimeException: The mail failed'] ?? null, 'A handler of a message dispatched, not received, is not kept.');
    }

    public function testTheHandlersAreForgottenAtTheReset(): void
    {
        $tracking = new Tracking();
        $listener = new WorkerFailureListener($tracking->capture);
        $received = new Envelope(new stdClass(), [new ReceivedStamp('async')]);
        $export = new LogicException('The export failed');
        $listener->onHandlerFailed(new HandlerFailureEvent($received, new HandlerDescriptor(static fn (): null => null, ['alias' => 'export']), $export));
        $listener->onHandlerFailed(new HandlerFailureEvent($received, new HandlerDescriptor(static fn (): null => null, ['alias' => 'other']), new LogicException('Other')));

        $listener->reset();
        $listener->onMessageFailed(new WorkerMessageFailedEvent($received, 'async', new HandlerFailedException($received, [$export])));

        self::assertArrayNotHasKey('handler', $tracking->store->onlyEvent()->context);
    }

    public function testEachAttemptWhenAsked(): void
    {
        $tracking = new Tracking();
        $retried = new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'async', new RuntimeException('Retried'));
        $retried->setForRetry();
        new WorkerFailureListener($tracking->capture, true)->onMessageFailed($retried);

        self::assertTrue($tracking->store->onlyEvent()->context['will_retry']);
        self::assertSame('RuntimeException: Retried', $tracking->store->onlyEvent()->title());
    }

    public function testAFailedAlertIsNeverCaptured(): void
    {
        $tracking = new Tracking();
        new WorkerFailureListener($tracking->capture, true)->onMessageFailed(new WorkerMessageFailedEvent(new Envelope(new AlertErrorGroup('f', 'first')), 'async', new RuntimeException('Chat down')));

        self::assertSame([], $tracking->store->groups);
    }

    public function testAnAlertIsQueuedWithIdentifiersOnly(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        new MessengerAlerter($bus)->alert($this->group(), Occurrence::Regression);

        self::assertEquals([new AlertErrorGroup(str_repeat('f', 64), 'regression')], $bus->messages);
    }

    public function testAQueuedAlertIsSent(): void
    {
        $store = new MemoryStore();
        $store->groups[str_repeat('f', 64)] = $this->group();
        $alerter = new RecordingAlerter();
        $handler = new AlertErrorGroupHandler($store, $alerter);

        $handler(new AlertErrorGroup(str_repeat('f', 64), 'first'));
        $handler(new AlertErrorGroup(str_repeat('0', 64), 'first'));
        $handler(new AlertErrorGroup(str_repeat('f', 64), 'unknown'));

        self::assertSame([[$store->groups[str_repeat('f', 64)], Occurrence::First]], $alerter->alerts);
    }

    public function testTheErrorOfACommandIsCaptured(): void
    {
        $tracking = new Tracking();
        $listener = new ConsoleErrorListener($tracking->capture);
        $listener->onConsoleError(new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), new RuntimeException('Import failed'), new Command('app:import')));

        $event = $tracking->store->onlyEvent();
        self::assertSame(Source::Console, $event->source);
        self::assertSame(['command' => 'app:import', 'exit_code' => 1], $event->context);

        $listener->onConsoleError(new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), new LogicException('No command')));
        self::assertCount(2, $tracking->store->groups);
    }

    public function testThePurge(): void
    {
        $store = new MemoryStore();
        $tester = new CommandTester(new PurgeCommand($store, new FrozenClock(), 30));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals(new DateTimeImmutable('2026-10-09T10:00:00+00:00'), $store->purgedBefore);
        self::assertSame("3 event(s) and group(s) removed.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '0']));
        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $store->purgedBefore);
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '99999']));
        self::assertSame('phil:error-tracking:purge', new PurgeCommand($store, new FrozenClock(), 30)->getName());
    }

    #[TestWith(['-1'])]
    #[TestWith(['01'])]
    #[TestWith(['100000'])]
    #[TestWith(['3 '])]
    #[TestWith(['x'])]
    #[TestWith(["3\n"])]
    public function testABadNumberOfDays(string $days): void
    {
        $tester = new CommandTester(new PurgeCommand(new MemoryStore(), new FrozenClock(), 30));

        self::assertSame(Command::INVALID, $tester->execute(['--older-than' => $days]));
        self::assertSame("The number of days is a whole number from 0 to 99999.\n", $tester->getDisplay());
    }

    public function testTheThrottle(): void
    {
        $throttle = new RateLimiterThrottle(new RateLimiterFactory(['id' => 't', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()));

        self::assertTrue($throttle->allow('a'));
        self::assertTrue($throttle->allow('a'));
        self::assertFalse($throttle->allow('a'));
        self::assertTrue($throttle->allow('b'));
    }

    private function group(): ErrorGroup
    {
        return new ErrorGroup(str_repeat('f', 64), 'RuntimeException: boom', 'App\Job::run (src/Job.php:3)', GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable(), '1.0.0');
    }
}
