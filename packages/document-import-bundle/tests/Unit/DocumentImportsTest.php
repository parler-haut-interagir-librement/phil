<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImportBundle\Application\Contract\DocumentScreening;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImports::class)]
#[CoversClass(DocumentImported::class)]
final class DocumentImportsTest extends TestCase
{
    private const string RTF = '{\rtf1\ansi{\stylesheet{\s0 Normal;}{\s1 Chapter Title;}{\s2 Author Note;}}\pard\s1 Hello\par\pard\s2 Secret\par\pard\s0 Body\par}';

    /** @var list<string> */
    private array $files = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                chmod($file, 0o600);
                unlink($file);
            }
        }
    }

    public function testAnImportGoesThroughTheStyleMapAndIsDispatchedWithItsReport(): void
    {
        $events = new RecordedEvents();
        $imported = $this->imports(events: $events)->import(self::RTF, filename: 'chapter.rtf');

        self::assertSame(Format::Rtf, $imported->format);
        self::assertSame([['header', ['text' => 'Hello', 'level' => 1]], ['paragraph', ['text' => 'Body']]], $this->blocks($imported->document));
        self::assertSame(['Paragraphs of styles the application leaves out are not imported: Author Note (1).'], $imported->report->summary());
        self::assertCount(1, $events->events);
        self::assertInstanceOf(DocumentImported::class, $events->events[0]);
        self::assertSame($imported, $events->events[0]->imported);
        self::assertSame('chapter.rtf', $events->events[0]->filename);
    }

    public function testTheReportIsAddedUnderTheConfiguredTitle(): void
    {
        $imports = $this->imports(title: 'Left out of the manuscript');
        $document = $imports->documentWithReport($imports->import(self::RTF, Format::Rtf));

        self::assertSame(['warning', ['title' => 'Left out of the manuscript', 'message' => 'Paragraphs of styles the application leaves out are not imported: Author Note (1).']], $this->blocks($document)[2]);
    }

    public function testByDefaultEveryFormatIsReadAndTheReportHasItsDefaultTitle(): void
    {
        $imports = new DocumentImports(new DocumentImporter(styles: new StyleMap(['Author Note' => StyleRole::Omit])), new DocumentExporter());
        $document = $imports->documentWithReport($imports->import(self::RTF));

        self::assertSame('What was not imported', $this->blocks($document)[2][1]['title'] ?? null);

        foreach ([Format::Docx, Format::Odt] as $format) {
            $exported = $imports->export(new Document([new Block('a', 'paragraph', ['text' => 'Hello'])]), $format);
            self::assertSame($format, $imports->import($exported->bytes)->format);
        }

        $flat = '<?xml version="1.0"?><office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:mimetype="application/vnd.oasis.opendocument.text"><office:body><office:text><text:p>Flat</text:p></office:text></office:body></office:document>';
        self::assertSame([['paragraph', ['text' => 'Flat']]], $this->blocks($imports->import($flat)->document));
        self::assertSame(Format::Markdown, $imports->import('# A', filename: 'a.md')->format);
        self::assertSame(Format::Html, $imports->import('<p>A</p>')->format);
        self::assertSame(Format::Pdf, $imports->import('%PDF-1.7 not a document')->format);
    }

    public function testAFormatTheApplicationDoesNotAcceptIsRefusedBeforeItIsRead(): void
    {
        $events = new RecordedEvents();
        $imports = $this->imports([Format::Docx, Format::Odt], events: $events);

        foreach ([['%PDF-1.7 broken', null, null, 'A PDF file is not accepted here; the formats accepted are DOCX, ODT.'], [self::RTF, null, 'chapter.rtf', 'A RTF file is not accepted here; the formats accepted are DOCX, ODT.'], ['<p>Hi</p>', Format::Html, null, 'A HTML file is not accepted here; the formats accepted are DOCX, ODT.']] as [$contents, $format, $filename, $message]) {
            try {
                $imports->import($contents, $format, $filename);
                self::fail($message);
            } catch (UnsupportedFormat $unsupportedFormat) {
                self::assertSame($message, $unsupportedFormat->getMessage());
            }
        }

        self::assertSame([], $events->events);
        $this->expectExceptionMessageIsOrContains('A RTF file is not accepted here; the formats accepted are none.');
        $this->imports([])->import(self::RTF);
    }

    public function testAWordBinaryDocumentIsRefusedWhateverItsName(): void
    {
        $this->expectException(UnsupportedFormat::class);
        $this->expectExceptionMessageIsOrContains('Word 97–2003');

        $this->imports()->import("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 100), filename: 'notes.rtf');
    }

    public function testAnUploadIsReadByItsClientName(): void
    {
        $path = $this->file('# Title');
        $imported = $this->imports()->importFile($path, filename: 'notes.md');

        self::assertSame(Format::Markdown, $imported->format);
        self::assertSame([['header', ['text' => 'Title', 'level' => 1]]], $this->blocks($imported->document));

        $named = $this->file(self::RTF, '.rtf');
        $events = new RecordedEvents();
        self::assertSame(Format::Rtf, $this->imports(events: $events)->importFile($named)->format);
        self::assertInstanceOf(DocumentImported::class, $events->events[0]);
        self::assertSame(basename($named), $events->events[0]->filename);
        self::assertSame(Format::Html, $this->imports()->importFile($this->file('<p>x</p>'), Format::Html, 'x.md')->format);
    }

    public function testAnUploadLargerThanTheLimitsIsRefusedBeforeItIsRead(): void
    {
        $limits = new Limits(totalBytes: \strlen(self::RTF));
        self::assertSame(Format::Rtf, $this->imports(limits: $limits)->importFile($this->file(self::RTF, '.rtf'))->format);

        $path = $this->file(self::RTF.' ', '.rtf');
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The file "%s" is larger than %d bytes.', basename($path), \strlen(self::RTF)));
        $this->imports(limits: $limits)->importFile($path);
    }

    public function testMarkdownAndHtmlLargerThanTheLimitsAreRefused(): void
    {
        $limits = new Limits(totalBytes: 7);
        self::assertSame(Format::Markdown, $this->imports(limits: $limits)->import('# Title', filename: 'a.md')->format);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The document is larger than 7 bytes.');
        $this->imports(limits: $limits)->import('<p>Too long</p>', Format::Html);
    }

    public function testAFileThatCannotBeReadIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The file "missing.docx" cannot be read.');

        $this->imports()->importFile(sys_get_temp_dir().'/phil/missing.docx');
    }

    public function testAFolderIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The file "%s" cannot be read.', basename(sys_get_temp_dir())));

        $this->imports()->importFile(sys_get_temp_dir());
    }

    public function testAnExportWritesOnlyTheAcceptedFormats(): void
    {
        $document = new Document([new Block('a', 'paragraph', ['text' => 'Hello'])]);
        $imports = $this->imports(exports: [Format::Odt]);
        $exported = $imports->export($document, Format::Odt, ['title' => 'Hello']);

        self::assertSame(Format::Odt, $exported->format);
        self::assertSame([['paragraph', ['text' => 'Hello']]], $this->blocks($this->imports()->import($exported->bytes, Format::Odt)->document));

        try {
            $imports->export($document, Format::Docx);
            self::fail('DOCX');
        } catch (ExportFailed $exportFailed) {
            self::assertSame('A document is not exported as DOCX here; the formats accepted are ODT.', $exportFailed->getMessage());
        }

        self::assertSame(Format::Docx, $this->imports()->export($document, Format::Docx)->format);
        $this->expectExceptionMessageIsOrContains('A document is not exported as PDF here; the formats accepted are DOCX, ODT.');
        $this->imports()->export($document, Format::Pdf);
    }

    public function testTheScreeningSeesTheFileAfterItsSizeAndFormatAndBeforeItIsParsed(): void
    {
        $screening = new RecordedScreening();
        $events = new RecordedEvents();
        $imports = $this->imports(events: $events, limits: new Limits(totalBytes: 200), screening: $screening);

        $imported = $imports->import(self::RTF, filename: 'chapter.rtf', claim: 'abc');
        self::assertSame([[self::RTF, 'chapter.rtf']], $screening->screened());
        self::assertSame('abc', $events->events[0] instanceof DocumentImported ? $events->events[0]->claim : null);
        self::assertSame(Format::Rtf, $imported->format);

        $imports->import('# Title', Format::Markdown);
        self::assertSame([[self::RTF, 'chapter.rtf'], ['# Title', 'document']], $screening->screened());
        self::assertNull($events->events[1] instanceof DocumentImported ? $events->events[1]->claim : 'none');

        $this->refused($imports, str_repeat('a', 201));
        $this->refused($imports, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 100));
        self::assertSame(2, $screening->times());

        $screening->refuse = true;

        try {
            $imports->import(self::RTF, filename: 'infected.rtf');
            self::fail('A file the screening refuses is imported.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('Refused.', $invalidDocument->getMessage());
        }

        self::assertCount(2, $events->events);
    }

    public function testAFileIsAcceptedByItsSizeAndFormatAlone(): void
    {
        $screening = new RecordedScreening();
        $imports = $this->imports(limits: new Limits(totalBytes: 10), screening: $screening);

        self::assertSame(Format::Markdown, $imports->accept('# Title', filename: 'a.md'));
        self::assertSame(Format::Html, $imports->accept('0123456789', Format::Html));
        self::assertSame([], $screening->screened());

        try {
            $imports->accept('01234567890', Format::Html);
            self::fail('A file larger than the limits is accepted.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The document is larger than 10 bytes.', $limitExceeded->getMessage());
        }

        $this->expectException(UnsupportedFormat::class);
        $imports->accept('%PDF-1.7', filename: 'a.pdf');
    }

    private function refused(DocumentImports $imports, string $contents): void
    {
        try {
            $imports->import($contents, filename: 'x.rtf');
            self::fail('A file refused is screened.');
        } catch (LimitExceeded|UnsupportedFormat $documentImportException) {
            self::assertNotSame('', $documentImportException->getMessage());
        }
    }

    public function testTheReportTitleIsTheConfiguredOne(): void
    {
        self::assertSame('Left out', $this->imports(title: 'Left out')->reportTitle());
    }

    /**
     * @param list<Format> $imports
     * @param list<Format> $exports
     */
    private function imports(array $imports = [Format::Docx, Format::Rtf, Format::Odt, Format::Markdown, Format::Html], array $exports = [Format::Docx, Format::Odt], string $title = 'What was not imported', ?RecordedEvents $events = null, Limits $limits = new Limits(), ?DocumentScreening $screening = null): DocumentImports
    {
        $importer = new DocumentImporter(limits: $limits, styles: new StyleMap(['Chapter Title' => StyleRole::Heading1, 'Author Note' => StyleRole::Omit]));

        return new DocumentImports($importer, new DocumentExporter(), $limits, $imports, $exports, $title, $events, $screening);
    }

    private function file(string $contents, string $extension = ''): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-imports-');
        $this->files[] = $path;

        if ('' !== $extension) {
            rename($path, $path.$extension);
            $path .= $extension;
            $this->files[] = $path;
        }

        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function blocks(Document $document): array
    {
        return array_map(static fn (Block $block): array => [$block->type, $block->data], $document->blocks);
    }
}
