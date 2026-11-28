<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Internal\BudgetFilter;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\ByteBudget;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Tests\Support\RefusingStream;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XMLReader;
use ZipArchive;

#[CoversClass(Values::class)]
#[CoversClass(RowBuilder::class)]
#[CoversClass(ByteBudget::class)]
#[CoversClass(BudgetFilter::class)]
#[CoversClass(SafeXmlReader::class)]
#[CoversClass(Output::class)]
#[CoversClass(BufferedFile::class)]
final class InternalTest extends TestCase
{
    use TemporaryFiles;

    /**
     * @return iterable<array{string, int|float|null}>
     */
    public static function numbers(): iterable
    {
        yield ['0', 0];
        yield ['-12', -12];
        yield ['+7', 7];
        yield ['123456789012345678', 123456789012345678];
        yield ['1234567890123456789', 1.2345678901234568E18];
        yield ['1.5', 1.5];
        yield ['1.', 1.0];
        yield ['.5', 0.5];
        yield ['-1.5e-3', -0.0015];
        yield ['2E3', 2000.0];
        yield ['1e999', null];
        yield ['', null];
        yield [' 1', null];
        yield ['1 ', null];
        yield ['1,5', null];
        yield ['0x1A', null];
        yield ['.', null];
        yield ['e3', null];
        yield ['1e', null];
        yield ["1\n", null];
    }

    #[DataProvider('numbers')]
    public function testANumberIsParsedStrictly(string $text, int|float|null $number): void
    {
        self::assertSame($number, Values::parseNumber($text));
    }

    /**
     * @return iterable<array{string, string|null}>
     */
    public static function isoDates(): iterable
    {
        yield ['2026-11-21', 'date:2026-11-21T00:00:00.000+00:00'];
        yield ['2026-11-21T14:30', 'date-time:2026-11-21T14:30:00.000+00:00'];
        yield ['2026-11-21 14:30:05', 'date-time:2026-11-21T14:30:05.000+00:00'];
        yield ['2026-11-21T14:30:05.123456789', 'date-time:2026-11-21T14:30:05.123+00:00'];
        yield ['2026-11-21T14:30:05,5', 'date-time:2026-11-21T14:30:05.500+00:00'];
        yield ['2026-11-21T14:30:05Z', 'date-time:2026-11-21T14:30:05.000+00:00'];
        yield ['2026-11-21T14:30:05-05:00', 'date-time:2026-11-21T14:30:05.000-05:00'];
        yield ['2026-11-21T14:30:05+0130', 'date-time:2026-11-21T14:30:05.000+01:30'];
        yield ['2024-02-29', 'date:2024-02-29T00:00:00.000+00:00'];
        yield ['2026-11-21T23:59:59', 'date-time:2026-11-21T23:59:59.000+00:00'];
        yield ['2026-02-29', null];
        yield ['2026-13-01', null];
        yield ['2026-00-10', null];
        yield ['2026-11-21T24:00', null];
        yield ['2026-11-21T23:60', null];
        yield ['2026-11-21T23:59:60', null];
        yield ['2026-11-21Z', null];
        yield ['26-11-21', null];
        yield ['2026-11-21T', null];
        yield [' 2026-11-21', null];
        yield ['2026-11-21T14', null];
    }

    #[DataProvider('isoDates')]
    public function testAnIsoDateIsReadOrRefused(string $text, ?string $cell): void
    {
        $read = Values::isoDate($text);

        self::assertSame($cell, $read instanceof Cell ? Workbooks::cell($read) : null);
    }

    public function testDatesAndNumbersHaveTheirText(): void
    {
        $date = new DateTimeImmutable('2026-11-21 04:05:06.120000');

        self::assertSame('2026-11-21', Values::date($date));
        self::assertSame('2026-11-21T04:05:06.12', Values::date($date, 'T'));
        self::assertSame('2026-11-21 04:05:00', Values::date(new DateTimeImmutable('2026-11-21 04:05'), ' '));
        self::assertSame('2026-11-21 04:05:00.000001', Values::date(new DateTimeImmutable('2026-11-21 04:05:00.000001'), ' '));
        self::assertSame('0.1', Values::number(0.1));
        self::assertSame('-3', Values::number(-3));
        self::assertSame('2026-11-21T04:05:06.120000+00:00', Values::wallClock(new DateTimeImmutable('2026-11-21 04:05:06.12', new DateTimeZone('Pacific/Kiritimati')))->format('Y-m-d\TH:i:s.uP'));
    }

    public function testARowFillsItsGapsAndTrimsItsEnd(): void
    {
        $builder = new RowBuilder(new ReadLimits(maxRows: 5, maxColumns: 4, maxCells: 7));

        self::assertNull($builder->row(1, []));
        self::assertNull($builder->row(9, [3 => Cell::empty(), 0 => Cell::empty()]));
        $row = $builder->row(2, [3 => Cell::text('x'), 1 => Cell::number(1)]);
        self::assertNotNull($row);
        self::assertSame(['empty:', 'number:1', 'empty:', 'text:x'], array_map(Workbooks::cell(...), $row->cells));
        $row = $builder->row(5, [0 => Cell::text('y'), 3 => Cell::empty()]);
        self::assertNotNull($row);
        self::assertSame(['text:y'], array_map(Workbooks::cell(...), $row->cells));
        self::assertSame(5, $builder->row(5, [1 => Cell::text('z')])?->number);

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The number of cells read goes over the limit of 7.');

        $builder->row(5, [0 => Cell::text('w')]);
    }

    public function testARowPastTheColumnsIsRefused(): void
    {
        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The number of columns of the row 1 goes over the limit of 4.');

        new RowBuilder(new ReadLimits(maxColumns: 4))->row(1, [4 => Cell::text('x')]);
    }

    public function testABudgetIsSpent(): void
    {
        $budget = new ByteBudget(3);

        self::assertTrue($budget->spend(3));
        self::assertFalse($budget->exceeded);
        self::assertFalse($budget->spend(1));
        self::assertTrue($budget->exceeded);
        self::assertSame(-1, $budget->remaining);
        self::assertFalse($budget->spend(0));
    }

    public function testTheFilterPassesTheBytesWithinTheBudget(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat('a', 10000));
        rewind($stream);
        $budget = new ByteBudget(10000);
        BudgetFilter::attach($stream, $budget);
        BudgetFilter::attach($stream, new ByteBudget(20000));

        self::assertSame(10000, \strlen((string) stream_get_contents($stream)));
        self::assertSame(0, $budget->remaining);
        self::assertContains(BudgetFilter::NAME, stream_get_filters());
    }

    public function testAnAttributeIsFoundByItsLocalName(): void
    {
        $reader = SafeXmlReader::fromString('<a xmlns="urn:a" xmlns:r="urn:r" x="1" r:id="rId1"><b/></a>', 'a.xml', 1000);

        self::assertTrue($reader->read());
        self::assertSame('rId1', $reader->attribute('id'));
        self::assertSame('1', $reader->attribute('x'));
        self::assertNull($reader->attribute('r'));
        self::assertSame('a', $reader->xml->localName);
        self::assertNull($reader->attribute('missing'));
        self::assertSame('a', $reader->xml->localName);
        self::assertTrue($reader->read());
        self::assertNull($reader->attribute('x'));
        self::assertTrue($reader->read());
        self::assertSame(XMLReader::END_ELEMENT, $reader->xml->nodeType);
        self::assertFalse($reader->read());
        self::assertFalse($reader->read());
        $reader->close();
        $reader->close();
    }

    public function testAnEmptyRootIsComplete(): void
    {
        $reader = SafeXmlReader::fromString('<a/>', 'a.xml', 100);

        self::assertTrue($reader->read());
        self::assertFalse($reader->read());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function brokenXml(): iterable
    {
        yield 'empty' => [''];
        yield 'not xml' => ['text'];
        yield 'open root' => ['<a><b/>'];
        yield 'mismatched' => ['<a><b></a>'];
        yield 'unknown entity' => ['<a>&x;</a>'];
        yield 'bytes no encoding reads' => ["<\0\0\0\0\0\0\0\0"];
    }

    #[DataProvider('brokenXml')]
    public function testBrokenXmlIsRefused(string $xml): void
    {
        $reader = SafeXmlReader::fromString($xml, 'broken.xml', 100);

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The part "broken.xml" is not well formed.');

        while ($reader->read()) {
        }
    }

    public function testABrokenTextIsRefusedWithoutAWarning(): void
    {
        $reader = SafeXmlReader::fromString('<a><v><?v</v></a>', 'broken.xml', 100);
        $reader->read();
        $reader->read();

        self::assertSame('', $reader->text());

        $this->expectException(ReadRefused::class);

        while ($reader->read()) {
        }
    }

    public function testTheTextOfAnElementIsReadWithoutMoving(): void
    {
        $reader = SafeXmlReader::fromString('<a>x<b>y</b></a>', 'a.xml', 100);
        $reader->read();

        self::assertSame('xy', $reader->text());
        self::assertSame('a', $reader->xml->localName);
    }

    public function testAFileWrittenInChunksKeepsEveryByte(): void
    {
        $path = $this->path('chunks');
        $file = new BufferedFile($path);
        $file->write(str_repeat('a', 65535));
        $file->write('b');
        self::assertSame(65536, filesize($path), 'A full chunk is written at once.');
        $file->write('c');
        $file->close();
        $file->discard();

        self::assertSame(str_repeat('a', 65535).'bc', file_get_contents($path));

        $discarded = new BufferedFile($this->path('discarded'));
        $discarded->write('lost');
        $discarded->discard();

        self::assertSame('', file_get_contents($this->path('discarded')));
    }

    public function testAFileThatCannotBeOpenedOrWrittenFails(): void
    {
        try {
            new BufferedFile($this->folder().'/missing/file');
            self::fail('No folder.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('A temporary file cannot be opened.', $writeFailed->getMessage());
        }

        RefusingStream::register();
        $file = new BufferedFile(RefusingStream::SCHEME.'://disk');
        $file->write('x');

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('A temporary file refused its bytes.');

        $file->close();
    }

    public function testTheTemporaryFilesAreMadeAndRemoved(): void
    {
        $path = Output::temporary($this->folder());

        self::assertFileExists($path);
        self::assertStringStartsWith(realpath($this->folder()).'/phil-spreadsheet-', $path);

        Output::remove($path, $this->path('never'));

        self::assertFileDoesNotExist($path);
    }

    public function testAnArchiveThatCannotBeWrittenFails(): void
    {
        try {
            Output::zip($this->folder().'/missing/a.zip', ['a' => 'b'], []);
            self::fail('No folder.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('The archive cannot be written to its temporary file.', $writeFailed->getMessage());
        }

        try {
            Output::zip($this->folder(), ['a' => 'b'], []);
            self::fail('A folder.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('The temporary file of the archive cannot be opened.', $writeFailed->getMessage());
        }

        try {
            Output::zip($this->path('a.zip'), ['a' => 'b'], ['part' => $this->path('never')]);
            self::fail('No part.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('The part "part" cannot be added to the archive.', $writeFailed->getMessage());
        }

        mkdir($this->path('folder'));

        try {
            Output::zip($this->path('b.zip'), ['a' => 'b'], ['part' => $this->path('folder')]);
            self::fail('A folder as a part.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('The archive cannot be written to its temporary file.', $writeFailed->getMessage());
        } finally {
            rmdir($this->path('folder'));
        }

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $this->expectExceptionMessageIsOrContains('The temporary file of the archive cannot be read.');

        Output::copy($this->path('never'), $stream);
    }

    public function testOnlyTheFirstTextIsStored(): void
    {
        $path = $this->path('a.zip');
        Output::zip($path, ['first' => str_repeat('a', 1000), 'second' => str_repeat('b', 1000)], [], true);
        $zip = new ZipArchive();
        $zip->open($path);

        self::assertSame(ZipArchive::CM_STORE, $zip->statName('first')['comp_method'] ?? null);
        self::assertSame(ZipArchive::CM_DEFLATE, $zip->statName('second')['comp_method'] ?? null);

        Output::zip($path, ['first' => str_repeat('a', 1000)], [], false);
        $zip->open($path);

        self::assertSame(ZipArchive::CM_DEFLATE, $zip->statName('first')['comp_method']);
    }
}
