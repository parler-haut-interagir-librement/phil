<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\CertificateUnavailable;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMediaBundle\Application\DocumentTemplates;
use Phil\PagedMediaBundle\Application\Event\PdfStored;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;
use Phil\PagedMediaBundle\Infrastructure\Messenger\PagedMediaHandler;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\FakeGotenberg;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\MemoryJobStore;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\RecordingStorage;
use Phil\PagedMediaBundle\Tests\Unit\Fixture\Ticket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

#[CoversClass(PagedMediaHandler::class)]
#[CoversClass(RenderPdf::class)]
#[CoversClass(RunPipeline::class)]
#[CoversClass(ConvertDocuments::class)]
#[CoversClass(PdfStored::class)]
#[CoversClass(RenderJob::class)]
#[CoversClass(PipelineJob::class)]
#[CoversClass(ConversionJob::class)]
#[CoversClass(JobUnavailable::class)]
final class PagedMediaHandlerTest extends TestCase
{
    private FakeGotenberg $gotenberg;

    private RecordingStorage $storage;

    private RecordingDispatcher $dispatcher;

    private MemoryJobStore $jobs;

    #[Override]
    protected function setUp(): void
    {
        $this->gotenberg = new FakeGotenberg();
        $this->storage = new RecordingStorage();
        $this->dispatcher = new RecordingDispatcher();
        $this->jobs = new MemoryJobStore();
    }

    public function testATemplateIsRenderedKeptAndAnnounced(): void
    {
        $this->handler()->render(new RenderPdf($this->jobs->keep(new RenderJob('ticket', ['seat' => 'A1'], 'tickets/a1.pdf', filename: 'a1.pdf'))));

        self::assertSame(new Ticket()->build(['seat' => 'A1'])->render(), $this->storage->stored['tickets/a1.pdf']->contents);
        self::assertEquals([new PdfStored('job-1', 'tickets/a1.pdf', 'a1.pdf', 'application/pdf')], $this->dispatcher->events);
        self::assertSame([], $this->gotenberg->requests);
    }

    public function testATemplateIsSignedAndTimestampedWhenAsked(): void
    {
        $this->gotenberg->answer('%PDF-signed', 200, ['Content-Type' => 'application/pdf', 'Gotenberg-Remediation-Residual' => '1', 'Gotenberg-Remediation-Rolled-Back' => '0']);

        $this->handler()->render(new RenderPdf($this->jobs->keep(new RenderJob('ticket', [], 'signed.pdf', new Pipeline(new Optimize()), true, true))));

        self::assertSame('%PDF-signed', $this->storage->stored['signed.pdf']->contents);
        self::assertStringContainsString('[{"op":"optimize"},{"op":"sign","certificate":"signer.p12","timestampUrl":"https://tsa.example.com"},{"op":"timestamp","timestampUrl":"https://tsa.example.com"}]', $this->gotenberg->requests[0]['body']);
        self::assertEquals([new PdfStored('job-1', 'signed.pdf', 'document.pdf', 'application/pdf', 1, 0)], $this->dispatcher->events);
    }

    public function testPdfsAreRunThroughTheStages(): void
    {
        $this->gotenberg->answer();

        $this->handler()->run(new RunPipeline($this->jobs->keep(new PipelineJob(Files::of(InputFile::fromContents('report.pdf', '%PDF')), new Pipeline(new Optimize()), 'out/report.pdf', true))));

        self::assertSame('%PDF-out', $this->storage->stored['out/report.pdf']->contents);
        self::assertStringContainsString('[{"op":"optimize"},{"op":"sign","certificate":"signer.p12","timestampUrl":"https://tsa.example.com"}]', $this->gotenberg->requests[0]['body']);
        self::assertEquals([new PdfStored('job-1', 'out/report.pdf', 'out.pdf', 'application/pdf')], $this->dispatcher->events);
    }

    public function testOfficeDocumentsAreConverted(): void
    {
        $this->gotenberg->answer();

        $this->handler()->convert(new ConvertDocuments($this->jobs->keep(new ConversionJob(Files::of(InputFile::fromContents('a.docx', 'DOCX')), 'a.pdf', new LibreOfficeOptions(landscape: true), null, false, true))));

        self::assertSame('%PDF-out', $this->storage->stored['a.pdf']->contents);
        self::assertStringContainsString('http://gotenberg:3000/forms/libreoffice/convert', $this->gotenberg->requests[0]['url']);
        self::assertStringContainsString('[{"op":"timestamp","timestampUrl":"https://tsa.example.com"}]', $this->gotenberg->requests[0]['body']);
        self::assertStringContainsString("name=\"landscape\"\r\n\r\ntrue", $this->gotenberg->requests[0]['body']);
    }

    public function testAJobCarriesItsDefaults(): void
    {
        $render = new RenderJob('ticket', [], 'a.pdf');
        $convert = new ConversionJob(Files::of(InputFile::fromContents('a.docx', 'D')), 'a.pdf');
        $run = new PipelineJob(Files::of(InputFile::fromContents('a.pdf', 'P')), new Pipeline(new Optimize()), 'a.pdf');

        self::assertNull($render->pipeline);
        self::assertFalse($render->sign);
        self::assertFalse($render->timestamp);
        self::assertSame('document.pdf', $render->filename);
        self::assertEquals(new LibreOfficeOptions(), $convert->options);
        self::assertNull($convert->pipeline);
        self::assertFalse($convert->sign);
        self::assertFalse($convert->timestamp);
        self::assertFalse($run->sign);
        self::assertFalse($run->timestamp);
    }

    public function testAJobIsForgottenOnceItsFileIsKept(): void
    {
        $this->jobs->keep(new RenderJob('ticket', [], 'kept.pdf'));
        $id = $this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'));

        $this->handler()->render(new RenderPdf($id));

        self::assertSame(['job-1'], array_keys($this->jobs->jobs));
        self::assertEquals([new PdfStored('job-2', 'a.pdf', 'document.pdf', 'application/pdf')], $this->dispatcher->events);
    }

    public function testAMissingJobIsNotRetried(): void
    {
        $exception = $this->failure(fn () => $this->handler()->render(new RenderPdf('job-9')));

        self::assertInstanceOf(UnrecoverableMessageHandlingException::class, $exception);
        self::assertSame('No job is kept under "job-9".', $exception->getMessage());
    }

    public function testAJobOfAnotherKindIsNotRetried(): void
    {
        $id = $this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'));

        $exception = $this->failure(fn () => $this->handler()->convert(new ConvertDocuments($id)));

        self::assertInstanceOf(UnrecoverableMessageHandlingException::class, $exception);
        self::assertSame('The job kept under "job-1" is not the kind its message handles.', $exception->getMessage());
        self::assertArrayHasKey('job-1', $this->jobs->jobs);
        self::assertSame([], $this->storage->stored);
    }

    public function testWithoutAnEventDispatcherTheFileIsStillKept(): void
    {
        new PagedMediaHandler($this->media(), $this->storage, $this->jobs)->render(new RenderPdf($this->jobs->keep(new RenderJob('ticket', [], 'a.pdf'))));

        self::assertArrayHasKey('a.pdf', $this->storage->stored);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testAGotenbergOutOfReachIsRetried(): void
    {
        $this->gotenberg->answer('Busy', 429, []);

        $exception = $this->failure(fn () => $this->handler()->run(new RunPipeline($this->jobs->keep(new PipelineJob(Files::of(InputFile::fromContents('a.pdf', 'P')), new Pipeline(new Optimize()), 'a.pdf')))));

        self::assertInstanceOf(RecoverableMessageHandlingException::class, $exception);
        self::assertSame('Gotenberg answered 429: Busy', $exception->getMessage());
        self::assertArrayHasKey('job-1', $this->jobs->jobs);
        self::assertSame([], $this->storage->stored);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testARefusedRequestIsNotRetried(): void
    {
        $this->gotenberg->answer('Invalid form data', 400, []);

        $exception = $this->failure(fn () => $this->handler()->run(new RunPipeline($this->jobs->keep(new PipelineJob(Files::of(InputFile::fromContents('a.pdf', 'P')), new Pipeline(new Optimize()), 'a.pdf')))));

        self::assertInstanceOf(UnrecoverableMessageHandlingException::class, $exception);
        self::assertSame('Gotenberg answered 400: Invalid form data', $exception->getMessage());
        self::assertNotNull($exception->getPrevious());
    }

    public function testAnUnknownTemplateIsNotRetried(): void
    {
        $exception = $this->failure(fn () => $this->handler()->render(new RenderPdf($this->jobs->keep(new RenderJob('badge', [], 'a.pdf')))));

        self::assertInstanceOf(UnrecoverableMessageHandlingException::class, $exception);
    }

    public function testAVaultOutOfReachIsRetried(): void
    {
        $certificates = new class implements CertificateSource {
            public function certificate(): Certificate
            {
                throw CertificateUnavailable::because('The vault could not be reached.');
            }
        };
        $handler = new PagedMediaHandler(new PagedMedia(new DocumentTemplates(['ticket' => new Ticket()]), $this->gotenberg->client, $certificates), $this->storage, $this->jobs);

        $message = new RenderPdf($this->jobs->keep(new RenderJob('ticket', [], 'a.pdf', sign: true)));

        $exception = $this->failure(static fn () => $handler->render($message));

        self::assertInstanceOf(RecoverableMessageHandlingException::class, $exception);
        self::assertSame('The vault could not be reached.', $exception->getMessage());
    }

    public function testAStorageThatCannotWriteIsRetried(): void
    {
        $storage = new class implements PdfStorage {
            public function store(string $path, Output $output): void
            {
                throw StorageFailed::because('Disk full.');
            }
        };

        $exception = $this->failure(fn () => new PagedMediaHandler($this->media(), $storage, $this->jobs, $this->dispatcher)->render(new RenderPdf($this->jobs->keep(new RenderJob('ticket', [], 'a.pdf')))));

        self::assertInstanceOf(RecoverableMessageHandlingException::class, $exception);
        self::assertSame([], $this->dispatcher->events);
    }

    private function failure(callable $handle): Throwable
    {
        try {
            $handle();
        } catch (Throwable $throwable) {
            return $throwable;
        }

        self::fail('The message was handled.');
    }

    private function handler(): PagedMediaHandler
    {
        return new PagedMediaHandler($this->media(), $this->storage, $this->jobs, $this->dispatcher);
    }

    private function media(): PagedMedia
    {
        $certificates = new class implements CertificateSource {
            public function certificate(): Certificate
            {
                return Certificate::pkcs12(InputFile::fromContents('signer.p12', 'p12'));
            }
        };

        return new PagedMedia(new DocumentTemplates(['ticket' => new Ticket()]), $this->gotenberg->client, $certificates, new TimestampAuthorityUrl('https://tsa.example.com'), events: $this->dispatcher);
    }
}
