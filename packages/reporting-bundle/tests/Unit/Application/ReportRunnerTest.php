<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Event\ReportExported;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRunner;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingDelivery;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TypeError;

#[CoversClass(ReportRunner::class)]
#[CoversClass(ReportExported::class)]
#[CoversClass(StoredExport::class)]
final class ReportRunnerTest extends TestCase
{
    private InMemoryJobStore $jobs;

    private InMemoryExportStore $exports;

    /** @var list<object> */
    private array $events = [];

    #[Override]
    protected function setUp(): void
    {
        $this->jobs = new InMemoryJobStore();
        $this->exports = new InMemoryExportStore();
    }

    public function testAJobWithoutDeliveryKeepsItsExport(): void
    {
        $id = $this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request()));
        $export = $this->runner()->run($id);

        self::assertSame(['sales-20261001-20261031.csv', 'text/csv; charset=UTF-8', 3, 97], [$export->filename, $export->mediaType, $export->rows, $export->size]);
        self::assertSame([$export->id], array_keys($this->exports->contents()));
        self::assertStringStartsWith("Day,Amount,Paid,Note\r\n", $this->exports->contents()[$export->id]);
        self::assertSame([], $this->jobs->ids());
        self::assertEquals([new ReportExported($id, $export->id, false)], $this->events);
    }

    public function testADeliveredExportIsForgotten(): void
    {
        $delivery = new RecordingDelivery();
        $job = new ReportJob('sales', 'json', Reporting::request(['rows' => 1]), 'record', ['ada@example.com']);
        $id = $this->jobs->keep($job);
        $export = $this->runner($delivery)->run($id);

        self::assertCount(1, $delivery->delivered);
        self::assertSame($job, $delivery->delivered[0][0]);
        self::assertSame($export, $delivery->delivered[0][1]);
        self::assertSame("[\n{\"Day\":\"2026-10-01\",\"Amount\":1.5,\"Paid\":false,\"Note\":\"=SUM(A1)\"}\n]\n", $delivery->delivered[0][2]);
        self::assertSame([], $this->exports->contents());
        self::assertSame([], $this->jobs->ids());
        self::assertEquals([new ReportExported($id, $export->id, true)], $this->events);
    }

    public function testTheStreamsAreClosedOnceUsed(): void
    {
        $store = new class(new InMemoryExportStore()) implements ExportStore {
            /** @var list<ReportStream> */
            public array $streams = [];

            public function __construct(
                private readonly InMemoryExportStore $store,
            ) {
            }

            #[Override]
            public function keep(ReportStream $content, string $filename, string $mediaType, int $rows): StoredExport
            {
                $this->streams[] = $content;

                return $this->store->keep($content, $filename, $mediaType, $rows);
            }

            #[Override]
            public function open(string $id): ReportStream
            {
                return $this->streams[] = $this->store->open($id);
            }

            #[Override]
            public function remove(string $id): void
            {
                $this->store->remove($id);
            }
        };
        $runner = new ReportRunner(Reporting::reports(), $this->jobs, $store, new ServiceLocator(['record' => static fn (): RecordingDelivery => new RecordingDelivery()]));
        $runner->run($this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'record')));

        $closed = 0;

        foreach ($store->streams as $stream) {
            try {
                $stream->contents();
            } catch (TypeError) {
                ++$closed;
            }
        }

        self::assertSame(2, $closed, 'The stream written to, then the stream delivered.');
    }

    public function testADeliveredExportIsForgottenByDefault(): void
    {
        $runner = new ReportRunner(Reporting::reports(), $this->jobs, $this->exports, new ServiceLocator(['record' => static fn (): RecordingDelivery => new RecordingDelivery()]));
        $runner->run($this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'record')));

        self::assertSame([], $this->exports->contents());
    }

    public function testADeliveredExportIsKeptWhenAsked(): void
    {
        $export = $this->runner(new RecordingDelivery(), keep: true)->run($this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'record')));

        self::assertSame([$export->id], array_keys($this->exports->contents()));
    }

    public function testAFailedDeliveryLeavesTheJobForARetry(): void
    {
        $id = $this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'record'));

        try {
            $this->runner(new RecordingDelivery(fails: true))->run($id);
            self::fail('Delivered.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Out of reach.', $runtimeException->getMessage());
        }

        self::assertSame([], $this->exports->contents());
        self::assertSame([$id], $this->jobs->ids());
        self::assertSame([], $this->events);
    }

    public function testAnUnknownDeliveryFailsBeforeTheExport(): void
    {
        $id = $this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(), 'fax'));

        try {
            $this->runner()->run($id);
            self::fail('Delivered.');
        } catch (NotFound $notFound) {
            self::assertSame('There is no report delivery "fax".', $notFound->getMessage());
        }

        self::assertSame([], $this->exports->contents());
        self::assertSame([$id], $this->jobs->ids());
    }

    public function testAFailedExportKeepsNothing(): void
    {
        $id = $this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request(['rows' => 200])));

        try {
            $this->runner()->run($id);
            self::fail('Exported.');
        } catch (ReportTooLarge $reportTooLarge) {
            self::assertStringContainsString('more than 100 rows', $reportTooLarge->getMessage());
        }

        self::assertSame([], $this->exports->contents());
        self::assertSame([$id], $this->jobs->ids());
    }

    public function testAJobGone(): void
    {
        $this->expectException(JobUnavailable::class);

        $this->runner()->run(str_repeat('0', 32));
    }

    public function testWithoutADispatcher(): void
    {
        $runner = new ReportRunner(Reporting::reports(), $this->jobs, $this->exports, new ServiceLocator([]));

        self::assertSame(3, $runner->run($this->jobs->keep(new ReportJob('sales', 'csv', Reporting::request())))->rows);
        self::assertSame([], $this->events);
    }

    private function runner(?RecordingDelivery $delivery = null, bool $keep = false): ReportRunner
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ReportExported::class, function (ReportExported $event): void {
            $this->events[] = $event;
        });

        return new ReportRunner(
            Reporting::reports(),
            $this->jobs,
            $this->exports,
            new ServiceLocator($delivery instanceof RecordingDelivery ? ['record' => static fn (): RecordingDelivery => $delivery] : []),
            $dispatcher,
            $keep,
        );
    }
}
