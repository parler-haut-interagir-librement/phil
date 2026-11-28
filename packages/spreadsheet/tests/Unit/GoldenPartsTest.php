<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use DateTimeImmutable;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Ods\OdsWriter;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\Xlsx\XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The parts the writers produce, byte for byte, for a workbook that holds each kind of cell, a frozen heading row, a
 * guarded text, a text with spaces at its ends and column widths. The expected parts are files of tests/Fixtures/golden.
 */
#[CoversClass(XlsxWriter::class)]
#[CoversClass(OdsWriter::class)]
#[CoversClass(Output::class)]
final class GoldenPartsTest extends TestCase
{
    use TemporaryFiles;

    private function workbook(): Workbook
    {
        return new Workbook(
            new Sheet('One', [
                ['a', 1, true],
                ['=x', Cell::date(new DateTimeImmutable('2024-01-02')), Cell::dateTime(new DateTimeImmutable('2024-01-02 03:04:05'))],
            ], [new Column('c1', 'C1', 12.5), new Column('c2', 'C2'), new Column('c3', 'C3', 7)]),
            new Sheet('Two', [[' b ']]),
        );
    }

    /**
     * @return array<string, string> the parts by name, in their order
     */
    private function written(Format $format, ?Workbook $workbook = null): array
    {
        $path = $this->path('book.'.$format->value);
        new Spreadsheets(temporaryDirectory: $this->folder())->writeFile($workbook ?? $this->workbook(), $path, $format);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CHECKCONS));
        $parts = [];

        for ($index = 0; $index < $zip->count(); ++$index) {
            $parts[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
        }

        $zip->close();

        return $parts;
    }

    private function golden(string $format, string $part): string
    {
        $name = $format.'--'.preg_replace('/[^A-Za-z0-9_.-]/', '_', str_replace('/', '__', $part));

        return (string) file_get_contents(__DIR__.'/../Fixtures/golden/'.$name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function parts(): iterable
    {
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $part) {
            yield 'xlsx '.$part => ['xlsx', $part];
        }

        foreach (['mimetype', 'META-INF/manifest.xml', 'styles.xml', 'content.xml'] as $part) {
            yield 'ods '.$part => ['ods', $part];
        }
    }

    #[DataProvider('parts')]
    public function testEachPartIsWrittenAsExpected(string $format, string $part): void
    {
        $parts = $this->written(Format::from($format));

        self::assertArrayHasKey($part, $parts);
        self::assertSame($this->golden($format, $part), $parts[$part]);
    }

    public function testTheArchivesHoldTheirPartsInTheirOrder(): void
    {
        self::assertSame(['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'], array_keys($this->written(Format::Xlsx)));
        self::assertSame(['mimetype', 'META-INF/manifest.xml', 'styles.xml', 'content.xml'], array_keys($this->written(Format::Ods)));
    }

    public function testOnlyTheMimetypeOfAnOdsFileIsStored(): void
    {
        $this->written(Format::Ods);
        $ods = new ZipArchive();
        $ods->open($this->path('book.ods'));
        self::assertSame(ZipArchive::CM_STORE, $ods->statIndex(0)['comp_method'] ?? null);
        $ods->close();

        $this->written(Format::Xlsx);
        $xlsx = new ZipArchive();
        $xlsx->open($this->path('book.xlsx'));
        self::assertNotSame(ZipArchive::CM_STORE, $xlsx->statIndex(0)['comp_method'] ?? null);
        $xlsx->close();
    }

    public function testAColumnWidthIsRoundedToThousandthsOfACentimetre(): void
    {
        $parts = $this->written(Format::Ods, new Workbook(new Sheet('One', [['a']], [new Column('a', 'A', 7.123)])));

        self::assertStringContainsString('style:column-width="1.425cm"', $parts['content.xml']);
    }

    public function testTheTemporaryDirectoryGivenIsTheOneUsed(): void
    {
        $missing = $this->path('missing');
        $stream = fopen('php://memory', 'w');
        self::assertIsResource($stream);

        foreach ([new OdsWriter($missing), new XlsxWriter($missing)] as $writer) {
            try {
                $writer->write($this->workbook(), $stream);
                self::fail('The directory does not exist.');
            } catch (WriteFailed $writeFailed) {
                self::assertStringContainsString($missing, $writeFailed->getMessage());
            }
        }
    }

    public function testAFileIsNotATemporaryDirectory(): void
    {
        $file = $this->file('plain', 'x');

        $this->expectException(WriteFailed::class);
        Output::temporary($file);
    }
}
