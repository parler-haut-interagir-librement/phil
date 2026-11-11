<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ReportingBundle\Application\Message\RunReport;
use Phil\ReportingBundle\Application\Message\RunScheduledReport;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRunner;
use Phil\ReportingBundle\Application\Report\Schedules;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Infrastructure\Messenger\ReportQueue;
use Phil\ReportingBundle\Infrastructure\Messenger\RunReportHandler;
use Phil\ReportingBundle\Infrastructure\Messenger\RunScheduledReportHandler;
use Phil\ReportingBundle\Infrastructure\Scheduler\ReportScheduleProvider;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingDelivery;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

#[CoversClass(ReportQueue::class)]
#[CoversClass(RunReportHandler::class)]
#[CoversClass(RunScheduledReportHandler::class)]
#[CoversClass(ReportScheduleProvider::class)]
final class MessengerTest extends TestCase
{
    public function testAQueuedReportCarriesItsIdentifierOnly(): void
    {
        $jobs = new InMemoryJobStore();
        $bus = $this->bus();
        $job = new ReportJob('sales', 'csv', Reporting::request(), 'mail', ['ada@example.com']);

        $id = new ReportQueue($jobs, $bus)->queue($job);

        self::assertEquals([new RunReport($id)], $bus->messages);
        self::assertSame($job, $jobs->find($id));
    }

    public function testTheHandlerRunsTheJob(): void
    {
        $jobs = new InMemoryJobStore();
        $exports = new InMemoryExportStore();
        $handler = new RunReportHandler(new ReportRunner(Reporting::reports(), $jobs, $exports, new ServiceLocator([])), $jobs);

        $handler(new RunReport($jobs->keep(new ReportJob('sales', 'csv', Reporting::request()))));

        self::assertSame([], $jobs->ids());
        self::assertCount(1, $exports->contents());
    }

    public function testAFailureOfTheBundleIsNeverRetried(): void
    {
        $jobs = new InMemoryJobStore();
        $handler = new RunReportHandler(new ReportRunner(Reporting::reports(), $jobs, new InMemoryExportStore(), new ServiceLocator([])), $jobs);
        $id = $jobs->keep(new ReportJob('nope', 'csv', Reporting::request(), null, ['ada@example.com']));

        try {
            $handler(new RunReport($id));
            self::fail('Handled.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('There is no report "nope".', $unrecoverableMessageHandlingException->getMessage());
            self::assertSame('There is no report "nope".', $unrecoverableMessageHandlingException->getPrevious()?->getMessage());
        }

        self::assertSame([], $jobs->ids());
    }

    public function testAnotherFailureIsLeftForARetry(): void
    {
        $jobs = new InMemoryJobStore();
        $handler = new RunReportHandler(new ReportRunner(Reporting::reports(), $jobs, new InMemoryExportStore(), new ServiceLocator(['record' => static fn (): RecordingDelivery => new RecordingDelivery(fails: true)])), $jobs);
        $id = $jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'record'));

        try {
            $handler(new RunReport($id));
            self::fail('Handled.');
        } catch (RuntimeException $runtimeException) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $runtimeException);
        }

        self::assertSame([$id], $jobs->ids());
    }

    public function testADueScheduleIsQueued(): void
    {
        $jobs = new InMemoryJobStore();
        $bus = $this->bus();
        $schedules = Schedules::fromConfig(['monthly' => ['report' => 'sales', 'format' => 'csv', 'every' => '1 month', 'from' => null, 'period' => 'last_month', 'delivery' => 'mail', 'recipients' => ['ada@example.com'], 'parameters' => []]], Reporting::periods());

        new RunScheduledReportHandler($schedules, new ReportQueue($jobs, $bus))(new RunScheduledReport('monthly'));

        self::assertCount(1, $jobs->ids());
        self::assertEquals([new RunReport($jobs->ids()[0])], $bus->messages);
        self::assertEquals($schedules->job('monthly'), $jobs->find($jobs->ids()[0]));
    }

    public function testTheSchedule(): void
    {
        $schedules = Schedules::fromConfig([
            'monthly' => ['report' => 'sales', 'format' => 'csv', 'every' => '1 month', 'from' => '2026-11-01 06:00:00', 'period' => 'last_month', 'delivery' => null, 'recipients' => [], 'parameters' => []],
            'daily' => ['report' => 'sales', 'format' => 'csv', 'every' => '1 day', 'from' => null, 'period' => 'yesterday', 'delivery' => null, 'recipients' => [], 'parameters' => []],
        ], Reporting::periods());

        $messages = new ReportScheduleProvider($schedules)->getSchedule()->getRecurringMessages();

        self::assertSame('phil_reporting', ReportScheduleProvider::NAME);
        self::assertCount(2, $messages);
        self::assertInstanceOf(PeriodicalTrigger::class, $messages[0]->getTrigger());
        self::assertEquals([new RunScheduledReport('monthly')], iterator_to_array($messages[0]->getProvider()->getMessages(new MessageContext('s', 'i', $messages[0]->getTrigger(), new DateTimeImmutable()))));
        self::assertSame('2026-11-01 06:00:00', $messages[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2026-10-15 00:00:00'))?->format('Y-m-d H:i:s'));
        self::assertSame(['every 1 month', 'every 1 day'], [(string) $messages[0]->getTrigger(), (string) $messages[1]->getTrigger()]);
        self::assertSame([], new ReportScheduleProvider(Schedules::fromConfig([], Reporting::periods()))->getSchedule()->getRecurringMessages());
    }

    /**
     * @return MessageBusInterface&object{messages: list<object>}
     */
    private function bus(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
    }
}
