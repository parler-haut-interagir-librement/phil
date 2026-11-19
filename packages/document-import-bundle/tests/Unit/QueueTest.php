<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use InvalidArgumentException;
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;
use Phil\DocumentImportBundle\Application\DocumentImportQueue;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\DocumentImportBundle\Application\ImportDocumentHandler;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Phil\DocumentImportBundle\Application\PendingImport;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Imports on a worker: the file kept by its claim, the message carrying the claim alone, the result or the refusal
 * dispatched with the claim, and the claim released once the library answered.
 */
#[CoversClass(DocumentImportQueue::class)]
#[CoversClass(ImportDocumentHandler::class)]
#[CoversClass(ImportDocument::class)]
#[CoversClass(PendingImport::class)]
#[CoversClass(DocumentImportFailed::class)]
final class QueueTest extends TestCase
{
    public function testAFileIsKeptAndOnlyItsClaimIsDispatched(): void
    {
        $pending = new MemoryPendingImports();
        $bus = new RecordedBus();
        $claim = new DocumentImportQueue($this->imports(), $pending, $bus)->submit('# Title', filename: 'Jane Doe — notes.md');

        self::assertSame('claim-1', $claim);
        self::assertEquals([new ImportDocument('claim-1')], $bus->messages);
        self::assertSame(['claim' => 'claim-1'], get_object_vars($bus->messages[0]));
        self::assertEquals(['claim-1' => new PendingImport('# Title', Format::Markdown, 'Jane Doe — notes.md')], $pending->imports);
    }

    public function testAFileTheImportsRefuseIsNeitherKeptNorDispatched(): void
    {
        $pending = new MemoryPendingImports();
        $bus = new RecordedBus();
        $queue = new DocumentImportQueue($this->imports(new Limits(totalBytes: 8)), $pending, $bus);

        self::assertSame('claim-1', $queue->submit('<p>x</p>', Format::Html));

        foreach (['<p>xy</p>', '%PDF-1.7'] as $refused) {
            try {
                $queue->submit($refused, filename: 'a.pdf');
                self::fail('A refused file is queued.');
            } catch (LimitExceeded|UnsupportedFormat) {
            }
        }

        self::assertCount(1, $bus->messages);
        self::assertCount(1, $pending->imports);
    }

    public function testTheWorkerImportsTheFileAndReleasesItsClaim(): void
    {
        $pending = new MemoryPendingImports();
        $events = new RecordedEvents();
        $claim = $pending->keep(new PendingImport('# Title', Format::Markdown, 'notes.md'));
        $handler = new ImportDocumentHandler($this->imports(events: $events), $pending, $events);

        $handler(new ImportDocument($claim));

        self::assertCount(1, $events->events);
        $event = $events->events[0];
        self::assertInstanceOf(DocumentImported::class, $event);
        self::assertSame($claim, $event->claim);
        self::assertSame('notes.md', $event->filename);
        self::assertSame(Format::Markdown, $event->imported->format);
        self::assertSame([], $pending->imports);
        self::assertSame([$claim], $pending->released);

        $handler(new ImportDocument($claim));
        self::assertCount(1, $events->events);
        self::assertSame([$claim], $pending->released);
    }

    public function testAFileTheLibraryRefusesIsReportedAndReleased(): void
    {
        $pending = new MemoryPendingImports();
        $events = new RecordedEvents();
        $claim = $pending->keep(new PendingImport('not a zip', Format::Docx));

        new ImportDocumentHandler($this->imports(), $pending, $events)(new ImportDocument($claim));

        self::assertCount(1, $events->events);
        $event = $events->events[0];
        self::assertInstanceOf(DocumentImportFailed::class, $event);
        self::assertSame($claim, $event->claim);
        self::assertInstanceOf(InvalidDocument::class, $event->reason);
        self::assertSame([], $pending->imports);
        self::assertSame([$claim], $pending->released);

        $quiet = new MemoryPendingImports();
        $other = $quiet->keep(new PendingImport('not a zip', Format::Docx));
        new ImportDocumentHandler($this->imports(), $quiet)(new ImportDocument($other));
        self::assertSame([$other], $quiet->released);
    }

    public function testAnyOtherErrorLeavesTheClaimForTheNextTry(): void
    {
        $pending = new MemoryPendingImports();
        $claim = $pending->keep(new PendingImport('# Title', Format::Markdown));
        $events = new RecordedEvents(fail: true);

        try {
            new ImportDocumentHandler($this->imports(events: $events), $pending, $events)(new ImportDocument($claim));
            self::fail('The error is hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed.', $runtimeException->getMessage());
        }

        self::assertArrayHasKey($claim, $pending->imports);
        self::assertSame([], $pending->released);
    }

    public function testAClaimIsShortAndPlain(): void
    {
        foreach (['a', 'A.b_c-9', str_repeat('f', 255)] as $claim) {
            self::assertSame($claim, new ImportDocument($claim)->claim);
        }

        foreach (['', str_repeat('f', 256), '../a', "a\n", 'a b', 'é'] as $claim) {
            try {
                new ImportDocument($claim);
                self::fail(json_encode($claim, \JSON_THROW_ON_ERROR));
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('The claim of an import is 1 to 255 letters, digits, ".", "_" or "-".', $invalidArgumentException->getMessage());
            }
        }
    }

    private function imports(Limits $limits = new Limits(), ?RecordedEvents $events = null): DocumentImports
    {
        return new DocumentImports(new DocumentImporter(limits: $limits), new DocumentExporter(), $limits, [Format::Docx, Format::Markdown, Format::Html], events: $events);
    }
}
