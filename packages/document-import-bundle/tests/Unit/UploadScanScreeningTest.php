<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\UploadScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The screening by the scanner of phil/upload-scan-bundle: a clean file passes, an infected one is refused, and one
 * that could not be scanned follows the policy.
 */
#[CoversClass(UploadScanScreening::class)]
final class UploadScanScreeningTest extends TestCase
{
    public function testACleanFilePasses(): void
    {
        $this->expectNotToPerformAssertions();

        new UploadScanScreening(new UploadScanner(new FakeScanner()))->screen('Plain text.', 'notes.md');
    }

    public function testAnInfectedFileIsRefused(): void
    {
        $events = new RecordedEvents();

        try {
            new UploadScanScreening(new UploadScanner(new FakeScanner(), events: $events))->screen('x'.FakeScanner::eicar(), 'chapter.docx');
            self::fail('An infected file passes.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('The document contains a threat and was refused.', $invalidDocument->getMessage());
        }

        self::assertCount(1, $events->events);
        $event = $events->events[0];
        self::assertInstanceOf(FileInfected::class, $event);
        self::assertSame('document_import', $event->context);
        self::assertSame('chapter.docx', $event->name);
    }

    public function testAFileThatCouldNotBeScannedFollowsThePolicy(): void
    {
        $down = new FakeScanner(error: 'Down.');

        try {
            new UploadScanScreening(new UploadScanner($down))->screen('Plain text.', 'notes.md');
            self::fail('An unscanned file passes under the policy reject.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('The document could not be checked for threats. Please try again later.', $invalidDocument->getMessage());
        }

        new UploadScanScreening(new UploadScanner($down, new ScanPolicy(OnScanError::AcceptAndFlag)))->screen('Plain text.', 'notes.md');
    }
}
