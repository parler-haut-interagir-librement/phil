<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\PagedMediaQueue;
use Phil\PagedMediaBundle\Application\PrintSubject;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\MemoryJobStore;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

#[CoversClass(PagedMediaQueue::class)]
#[CoversClass(UnnamedPrintStarting::class)]
#[CoversClass(RenderPdf::class)]
#[CoversClass(RunPipeline::class)]
#[CoversClass(ConvertDocuments::class)]
#[CoversClass(RenderJob::class)]
#[CoversClass(PipelineJob::class)]
#[CoversClass(ConversionJob::class)]
final class PagedMediaQueueTest extends TestCase
{
    public function testARenderingIsKeptAsAJobAndItsMessageCarriesOnlyTheIdentifier(): void
    {
        $bus = new RecordingBus();
        $jobs = new MemoryJobStore();
        $pipeline = new Pipeline(new Optimize());

        $id = new PagedMediaQueue($bus, $jobs)->render('badge', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'], 'badges/1.pdf', $pipeline, true, true, 'badge.pdf');

        self::assertSame('job-1', $id);
        self::assertEquals([new RenderPdf('job-1')], $bus->messages);
        self::assertEquals(['job-1' => new RenderJob('badge', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'], 'badges/1.pdf', $pipeline, true, true, 'badge.pdf')], $jobs->jobs);
        self::assertSame(['job' => 'job-1'], get_object_vars($bus->messages[0]));
        self::assertStringNotContainsString('Ada', serialize($bus->messages[0]));
    }

    public function testAPrintIsAnnouncedBeforeItIsQueued(): void
    {
        $subject = new PrintSubject('book', '42');
        $files = Files::of(InputFile::fromContents('a.pdf', '%PDF'));
        $events = new RecordingDispatcher();
        $queue = new PagedMediaQueue(new RecordingBus(), new MemoryJobStore(), $events);

        $queue->render('badge', [], 'a.pdf', subject: $subject);
        $queue->run($files, new Pipeline(new Optimize()), 'b.pdf', subject: $subject);
        $queue->convert($files, 'c.pdf', subject: $subject);
        $queue->render('badge', [], 'd.pdf');

        self::assertEquals([...array_fill(0, 3, new PrintStarting($subject)), new UnnamedPrintStarting()], $events->events);
        self::assertSame('job-1', new PagedMediaQueue(new RecordingBus(), new MemoryJobStore())->render('badge', [], 'e.pdf', subject: $subject));
    }

    public function testAListenerThatThrowsKeepsThePrintOutOfTheQueue(): void
    {
        $bus = new RecordingBus();
        $jobs = new MemoryJobStore();
        $events = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('Refused.');
            }
        };
        $queue = new PagedMediaQueue($bus, $jobs, $events);
        $subject = new PrintSubject('book', '42');
        $files = Files::of(InputFile::fromContents('a.pdf', '%PDF'));

        foreach ([
            static fn (): string => $queue->render('badge', [], 'a.pdf', subject: $subject),
            static fn (): string => $queue->run($files, new Pipeline(new Optimize()), 'b.pdf', subject: $subject),
            static fn (): string => $queue->convert($files, 'c.pdf', subject: $subject),
        ] as $print) {
            try {
                $print();
                self::fail('The print was not stopped.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame('Refused.', $runtimeException->getMessage());
            }
        }

        self::assertSame([], $jobs->jobs);
        self::assertSame([], $bus->messages);
    }

    public function testARenderingTakesItsDefaults(): void
    {
        $jobs = new MemoryJobStore();

        new PagedMediaQueue(new RecordingBus(), $jobs)->render('ticket', [], 'a.pdf');

        self::assertEquals(['job-1' => new RenderJob('ticket', [], 'a.pdf', null, false, false, 'document.pdf')], $jobs->jobs);
    }

    public function testStagesOnPdfsAreKeptAsAJob(): void
    {
        $bus = new RecordingBus();
        $jobs = new MemoryJobStore();
        $files = Files::of(InputFile::fromContents('contract.pdf', '%PDF-private'));
        $pipeline = new Pipeline(new Optimize());
        $queue = new PagedMediaQueue($bus, $jobs);

        self::assertSame('job-1', $queue->run($files, $pipeline, 'out/a.pdf', true, true));
        self::assertSame('job-2', $queue->run($files, $pipeline, 'out/b.pdf'));
        self::assertEquals([new RunPipeline('job-1'), new RunPipeline('job-2')], $bus->messages);
        self::assertEquals(['job-1' => new PipelineJob($files, $pipeline, 'out/a.pdf', true, true), 'job-2' => new PipelineJob($files, $pipeline, 'out/b.pdf', false, false)], $jobs->jobs);
        self::assertStringNotContainsString('private', serialize($bus->messages));
    }

    public function testAConversionIsKeptAsAJob(): void
    {
        $bus = new RecordingBus();
        $jobs = new MemoryJobStore();
        $documents = Files::of(InputFile::fromContents('a.docx', 'DOCX'));
        $options = new LibreOfficeOptions(password: 'open');
        $pipeline = new Pipeline(new Optimize());
        $queue = new PagedMediaQueue($bus, $jobs);

        self::assertSame('job-1', $queue->convert($documents, 'a.pdf', $options, $pipeline, true, true));
        self::assertSame('job-2', $queue->convert($documents, 'b.pdf'));
        self::assertEquals([new ConvertDocuments('job-1'), new ConvertDocuments('job-2')], $bus->messages);
        self::assertEquals(['job-1' => new ConversionJob($documents, 'a.pdf', $options, $pipeline, true, true), 'job-2' => new ConversionJob($documents, 'b.pdf', new LibreOfficeOptions(), null, false, false)], $jobs->jobs);
        self::assertStringNotContainsString('open', serialize($bus->messages));
    }
}
