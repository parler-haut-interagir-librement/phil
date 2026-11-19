<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Zip;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Report;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The edges of the public classes: the boundaries of the limits, the defaults, what an export leaves behind.
 */
#[CoversClass(DocumentImporter::class)]
#[CoversClass(DocumentExporter::class)]
#[CoversClass(Format::class)]
#[CoversClass(Imported::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Media::class)]
#[CoversClass(Report::class)]
#[CoversClass(StyleMap::class)]
final class PublicApiTest extends TestCase
{
    public function testTheMediaTypeIsReadWithoutAnImage(): void
    {
        self::assertSame('image/gif', Media::mediaTypeOf('GIF89a'));
    }

    public function testAKindWithoutDetailCountsItsOmissions(): void
    {
        $report = new Report();
        $report->add(Omitted::Macro);
        $report->add(Omitted::Macro);
        $report->add(Omitted::Macro);

        self::assertSame(['Macros are never run nor imported (3).'], $report->summary());
    }

    public function testStylesAreNamedWhateverTheirCase(): void
    {
        $map = new StyleMap(['ÉNONCÉ' => StyleRole::Quote]);

        self::assertSame(StyleRole::Quote, $map->role('énoncé'));
        self::assertSame(StyleRole::Code, $map->with(' Énoncé ', StyleRole::Code)->role('ÉNONCÉ'));
    }

    public function testTheReportTakesTheFirstFreeIdentifier(): void
    {
        $report = new Report();
        $report->add(Omitted::Macro);

        $document = new Document([new Block('import-report', 'paragraph', ['text' => 'a'])]);

        self::assertSame('import-report-2', new Imported(Format::Html, $document, [], [], $report)->documentWithReport()->blocks[1]->id);
    }

    public function testAFileOfExactlyTheLimitIsRead(): void
    {
        $rtf = '{\rtf1 Hello}';

        self::assertSame(Format::Rtf, new DocumentImporter(limits: new Limits(totalBytes: \strlen($rtf)))->import($rtf, Format::Rtf)->format);

        $this->expectExceptionObject(LimitExceeded::because(\sprintf('The file is larger than %d bytes.', \strlen($rtf) - 1)));
        new DocumentImporter(limits: new Limits(totalBytes: \strlen($rtf) - 1))->import($rtf, Format::Rtf);
    }

    public function testMarkdownAndHtmlAreBoundedToo(): void
    {
        self::assertSame(Format::Markdown, new DocumentImporter(limits: new Limits(totalBytes: 7))->import('# Title', Format::Markdown)->format);

        $this->expectExceptionObject(LimitExceeded::because('The file is larger than 7 bytes.'));
        new DocumentImporter(limits: new Limits(totalBytes: 7))->import('<p>Long</p>', Format::Html);
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame([64 * 1024 * 1024, 256 * 1024 * 1024, 100, 500_000, 1_000_000], [$limits->entryBytes, $limits->totalBytes, $limits->depth, $limits->objects, $limits->operations]);
    }

    public function testDetectingRefusesACompoundFile(): void
    {
        $this->expectException(UnsupportedFormat::class);
        Format::detect("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 64), 'notes.rtf');
    }

    public function testTheBytesAreLookedAtOverTheirFirstFourKilobytes(): void
    {
        self::assertSame(Format::Rtf, Format::detect(str_repeat(' ', 4091).'{\rtf1 x}'));
        self::assertSame(Format::Html, Format::detect(str_repeat(' ', 4095).'<p>x</p>'));

        $this->expectException(UnsupportedFormat::class);
        Format::detect(str_repeat(' ', 4096).'<p>x</p>');
    }

    public function testAnExportStoresTheMimetypeDeflatesTheRestAndDatesEveryEntry(): void
    {
        $stats = $this->stats(new DocumentExporter()->export(new Document([new Block('a', 'paragraph', ['text' => 'Hello'])]), Format::Odt)->bytes);

        self::assertGreaterThan(3, \count($stats));

        foreach ($stats as $stat) {
            self::assertSame('mimetype' === $stat['name'] ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE, $stat['comp_method'], $stat['name']);
            // The first date of a ZIP archive, as the platform's library reads it back: in the early 1980s.
            self::assertLessThan(400_000_000, $stat['mtime'], $stat['name']);
            self::assertSame($stats[0]['mtime'], $stat['mtime']);
        }
    }

    public function testAnExportLeavesNothingInItsDirectory(): void
    {
        if (Zip::inMemory()) {
            self::markTestSkipped('This PHP writes the archive in memory.');
        }

        $directory = sys_get_temp_dir().'/phil-export-'.bin2hex(random_bytes(4));
        mkdir($directory);

        try {
            new DocumentExporter(temporaryDirectory: $directory)->export(new Document(), Format::Docx);
            self::assertSame(['.', '..'], scandir($directory));
        } finally {
            rmdir($directory);
        }
    }

    public function testAnExportIsWrittenInItsTemporaryDirectory(): void
    {
        if (Zip::inMemory()) {
            self::markTestSkipped('This PHP writes the archive in memory.');
        }

        $directory = sys_get_temp_dir().'/phil-export-'.bin2hex(random_bytes(4));
        mkdir($directory, 0o500);
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        });

        try {
            new DocumentExporter(temporaryDirectory: $directory)->export(new Document(), Format::Docx);
        } finally {
            restore_error_handler();
            rmdir($directory);
        }

        self::assertCount(1, $notices);
        self::assertStringContainsString('temporary directory', $notices[0]);
    }

    /**
     * @return list<array{name: string, comp_method: int, mtime: int}>
     */
    private function stats(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-zip');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        $stats = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            self::assertIsArray($stat);
            $stats[] = ['name' => $stat['name'], 'comp_method' => $stat['comp_method'], 'mtime' => $stat['mtime']];
        }

        $zip->close();
        unlink($path);

        return $stats;
    }
}
