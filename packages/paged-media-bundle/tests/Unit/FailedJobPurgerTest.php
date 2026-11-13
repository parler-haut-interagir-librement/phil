<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Override;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Phil\PagedMediaBundle\Infrastructure\Messenger\FailedJobPurger;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\MemoryJobStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SyncMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[CoversClass(FailedJobPurger::class)]
final class FailedJobPurgerTest extends TestCase
{
    private MemoryJobStore $jobs;

    private FailedJobPurger $purger;

    #[Override]
    protected function setUp(): void
    {
        $this->jobs = new MemoryJobStore();
        $this->purger = new FailedJobPurger($this->jobs);
    }

    public function testAMessageFailedForGoodInAWorkerForgetsItsJob(): void
    {
        $files = Files::of(InputFile::fromContents('a.pdf', '%PDF'));
        $render = $this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'));
        $run = $this->jobs->keep(new PipelineJob($files, new Pipeline(new Optimize()), 'b.pdf'));
        $convert = $this->jobs->keep(new ConversionJob($files, 'c.pdf'));

        $retried = $this->workerEvent(new RenderPdf($render));
        $retried->setForRetry();

        $this->purger->onWorkerMessageFailed($retried);
        self::assertArrayHasKey($render, $this->jobs->jobs, 'A message that will be retried keeps its job.');

        foreach ([new RenderPdf($render), new RunPipeline($run), new ConvertDocuments($convert)] as $message) {
            $this->purger->onWorkerMessageFailed($this->workerEvent($message));
        }

        self::assertSame([], $this->jobs->jobs);
    }

    public function testAMessageFailedForGoodOnTheSyncTransportForgetsItsJob(): void
    {
        $job = $this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'));

        $this->purger->onSyncMessageFailed(new SyncMessageFailedEvent(new Envelope(new RenderPdf($job)), 'sync', new RuntimeException('Failed.'), true));
        self::assertArrayHasKey($job, $this->jobs->jobs);

        $this->purger->onSyncMessageFailed(new SyncMessageFailedEvent(new Envelope(new RenderPdf($job)), 'sync', new RuntimeException('Failed.'), false));
        self::assertSame([], $this->jobs->jobs);
    }

    public function testAnotherMessageIsIgnored(): void
    {
        $job = $this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'));

        $this->purger->onWorkerMessageFailed($this->workerEvent(new stdClass()));
        $this->purger->onSyncMessageFailed(new SyncMessageFailedEvent(new Envelope(new stdClass()), 'sync', new RuntimeException('Failed.'), false));

        self::assertArrayHasKey($job, $this->jobs->jobs);
    }

    public function testAJobTheStoreCannotRemoveIsLeft(): void
    {
        $store = new class implements JobStore {
            public int $removals = 0;

            #[Override]
            public function keep(RenderJob|PipelineJob|ConversionJob $job): string
            {
                return 'job';
            }

            #[Override]
            public function find(string $id): RenderJob|PipelineJob|ConversionJob
            {
                throw JobUnavailable::because('No job.');
            }

            #[Override]
            public function remove(string $id): void
            {
                ++$this->removals;

                throw StorageFailed::because('The job cannot be removed.');
            }
        };

        new FailedJobPurger($store)->onWorkerMessageFailed($this->workerEvent(new RenderPdf('job')));

        self::assertSame(1, $store->removals);
    }

    private function workerEvent(object $message): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(new Envelope($message), 'async', new RuntimeException('Failed.'));
    }
}
