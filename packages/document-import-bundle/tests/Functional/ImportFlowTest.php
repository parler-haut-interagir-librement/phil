<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Functional;

use Override;
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Format;
use Phil\DocumentImportBundle\Application\DocumentImportQueue;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use Phil\DocumentImportBundle\PhilDocumentImportBundle;
use Phil\DocumentImportBundle\Tests\Functional\App\RecordedImports;
use Phil\DocumentImportBundle\Tests\Functional\App\TestKernel;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\UploadScan\FakeScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Twig\Environment;

/**
 * The whole path in a booted application: a file scanned before it is parsed, a file queued with a message that
 * carries its claim alone, the worker's import dispatched with that claim, and the report rendered by Twig.
 */
#[CoversClass(PhilDocumentImportBundle::class)]
final class ImportFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testAnInfectedFileIsRefusedBeforeItIsParsed(): void
    {
        try {
            $this->imports()->import('# Title'."\n\n".FakeScanner::eicar(), filename: 'notes.md');
            self::fail('An infected file is imported.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('The document contains a threat and was refused.', $invalidDocument->getMessage());
        }

        self::assertSame([], $this->events());
        self::assertSame(Format::Markdown, $this->imports()->import('# Title', filename: 'notes.md')->format);
    }

    public function testAQueuedFileIsImportedByTheWorkerWithItsClaimAlone(): void
    {
        $queue = self::getContainer()->get(DocumentImportQueue::class);
        self::assertInstanceOf(DocumentImportQueue::class, $queue);

        $claim = $queue->submit(new DocumentExporter()->export(new Document([new Block('a', 'paragraph', ['text' => 'Hello'])]), Format::Docx)->bytes, filename: 'Jane Doe — chapter.docx');

        $transport = self::getContainer()->get('messenger.transport.imports');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertEquals(new ImportDocument($claim), $message);
        self::assertStringNotContainsString('Jane', serialize($message));
        self::assertSame([], $this->events());

        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $bus->dispatch($message, [new ReceivedStamp('imports')]);

        self::assertCount(1, $this->events());
        $event = $this->events()[0];
        self::assertInstanceOf(DocumentImported::class, $event);
        self::assertSame($claim, $event->claim);
        self::assertSame('Jane Doe — chapter.docx', $event->filename);
        self::assertSame(['paragraph'], array_map(static fn (Block $block): string => $block->type, $event->imported->document->blocks));

        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);
        self::assertSame([], array_values(array_diff((array) scandir($kernel->pendingDirectory()), ['.', '..'])));
    }

    public function testAQueuedFileTheLibraryRefusesIsReportedByItsClaim(): void
    {
        $queue = self::getContainer()->get(DocumentImportQueue::class);
        self::assertInstanceOf(DocumentImportQueue::class, $queue);
        $claim = $queue->submit('not a zip', Format::Docx);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $bus->dispatch(new ImportDocument($claim), [new ReceivedStamp('imports')]);

        self::assertCount(1, $this->events());
        $event = $this->events()[0];
        self::assertInstanceOf(DocumentImportFailed::class, $event);
        self::assertSame($claim, $event->claim);
    }

    public function testTheReportIsRenderedByTwigUnderTheConfiguredTitle(): void
    {
        $imported = $this->imports()->import('<p>Text</p><script>alert(1)</script>', Format::Html);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('@PhilDocumentImport/report.html.twig', ['report' => $imported->report]);

        self::assertStringContainsString('<h2 id="document-import-report-title">Left out</h2>', $html);
        self::assertSame(\count($imported->report->summary()), substr_count($html, '<li>'));
        self::assertSame('Left out', $this->imports()->reportTitle());
    }

    /**
     * @return list<DocumentImported|DocumentImportFailed>
     */
    private function events(): array
    {
        $recorded = self::getContainer()->get(RecordedImports::class);
        self::assertInstanceOf(RecordedImports::class, $recorded);

        return $recorded->events();
    }

    private function imports(): DocumentImports
    {
        $imports = self::getContainer()->get(DocumentImports::class);
        self::assertInstanceOf(DocumentImports::class, $imports);

        return $imports;
    }
}
