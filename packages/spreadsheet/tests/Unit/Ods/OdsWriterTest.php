<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Ods;

use DateTimeImmutable;
use Dom\XMLDocument;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Ods\OdsText;
use Phil\Spreadsheet\Ods\OdsWriter;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(OdsWriter::class)]
#[CoversClass(OdsText::class)]
#[CoversClass(BufferedFile::class)]
#[CoversClass(Output::class)]
#[CoversClass(SheetRows::class)]
#[CoversClass(Values::class)]
final class OdsWriterTest extends TestCase
{
    use TemporaryFiles;

    private const string TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    /**
     * @return array<string, string>
     */
    private function parts(Workbook $workbook, WriteOptions $options = new WriteOptions()): array
    {
        $path = $this->path('book.ods');
        $stream = fopen($path, 'w');
        self::assertIsResource($stream);
        new OdsWriter($this->folder())->write($workbook, $stream, $options);
        fclose($stream);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CHECKCONS));
        $parts = [];

        for ($index = 0; $index < $zip->count(); ++$index) {
            $parts[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
        }

        self::assertSame(ZipArchive::CM_STORE, $zip->statIndex(0)['comp_method'] ?? null, 'The mimetype is stored.');
        $zip->close();
        self::assertSame(['book.ods'], $this->files(), 'No temporary file is left.');

        return $parts;
    }

    private function content(Workbook $workbook, WriteOptions $options = new WriteOptions()): string
    {
        return $this->parts($workbook, $options)['content.xml'];
    }

    public function testTheArchiveHoldsTheWellFormedPartsOfASpreadsheet(): void
    {
        self::assertSame(Format::Ods, new OdsWriter()->format());

        $parts = $this->parts(new Workbook(new Sheet('One & "two"', [['a']]), new Sheet('Second', [[1]])));

        self::assertSame(['mimetype', 'META-INF/manifest.xml', 'styles.xml', 'content.xml'], array_keys($parts));
        self::assertSame('application/vnd.oasis.opendocument.spreadsheet', $parts['mimetype']);

        foreach (['META-INF/manifest.xml', 'styles.xml', 'content.xml'] as $name) {
            self::assertInstanceOf(XMLDocument::class, XMLDocument::createFromString($parts[$name]), $name);
        }

        $manifest = XMLDocument::createFromString($parts['META-INF/manifest.xml']);
        $entries = [];

        foreach ($manifest->getElementsByTagName('manifest:file-entry') as $entry) {
            $entries[(string) $entry->getAttribute('manifest:full-path')] = $entry->getAttribute('manifest:media-type');
        }

        self::assertSame(['/' => 'application/vnd.oasis.opendocument.spreadsheet', 'content.xml' => 'text/xml', 'styles.xml' => 'text/xml'], $entries);
        self::assertStringContainsString('office:version="1.3"', $parts['styles.xml']);

        $content = XMLDocument::createFromString($parts['content.xml']);
        $names = [];

        foreach ($content->getElementsByTagNameNS(self::TABLE, 'table') as $table) {
            $names[] = $table->getAttributeNS(self::TABLE, 'name');
        }

        self::assertSame(['One & "two"', 'Second'], $names);
    }

    public function testEachCellIsWrittenWithItsType(): void
    {
        $row = ['00123', 12, 1.5, true, false, Cell::date(new DateTimeImmutable('2026-11-21')), new DateTimeImmutable('2026-11-21 12:00:01.5'), null, '=1+1', null, null];

        self::assertStringContainsString('<table:table-row>'
            .'<table:table-cell office:value-type="string"><text:p>00123</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="float" office:value="12"/>'
            .'<table:table-cell office:value-type="float" office:value="1.5"/>'
            .'<table:table-cell office:value-type="boolean" office:boolean-value="true"/>'
            .'<table:table-cell office:value-type="boolean" office:boolean-value="false"/>'
            .'<table:table-cell table:style-name="ce1" office:value-type="date" office:date-value="2026-11-21"/>'
            .'<table:table-cell table:style-name="ce2" office:value-type="date" office:date-value="2026-11-21T12:00:01.5"/>'
            .'<table:table-cell/>'
            .'<table:table-cell office:value-type="string"><text:p>=1+1</text:p></table:table-cell>'
            .'</table:table-row></table:table>', $this->content(Workbook::single('Data', [$row])));
        self::assertStringContainsString('<table:table-row></table:table-row>', $this->content(Workbook::single('Data', [[null]])));
    }

    public function testTheHeadingsAreBoldAndTheWidthsSet(): void
    {
        $columns = [new Column('a', 'Name', 20), new Column('b'), new Column('c', width: 8.5), new Column('d', width: 20)];
        $content = $this->content(new Workbook(Workbook::single('Data', [['x']], $columns)->sheets[0], new Sheet('Other', [], [new Column('e', width: 12)])));

        self::assertStringContainsString('<style:style style:name="ce3" style:family="table-cell"><style:text-properties fo:font-weight="bold"/></style:style>', $content);
        self::assertStringContainsString('<style:style style:name="co1" style:family="table-column"><style:table-column-properties style:column-width="4cm"/></style:style>'
            .'<style:style style:name="co2" style:family="table-column"><style:table-column-properties style:column-width="1.7cm"/></style:style>'
            .'<style:style style:name="co3" style:family="table-column"><style:table-column-properties style:column-width="2.4cm"/></style:style></office:automatic-styles>', $content);
        self::assertStringContainsString('<table:table table:name="Data"><table:table-column table:style-name="co1"/><table:table-column/><table:table-column table:style-name="co2"/><table:table-column table:style-name="co1"/>'
            .'<table:table-row><table:table-cell table:style-name="ce3" office:value-type="string"><text:p>Name</text:p></table:table-cell>', $content);
        self::assertStringContainsString('<table:table table:name="Other"><table:table-column table:style-name="co3"/><table:table-row><table:table-cell table:style-name="ce3" office:value-type="string"><text:p>e</text:p></table:table-cell></table:table-row></table:table>', $content);
        self::assertStringContainsString('<number:date-style style:name="N1"><number:year number:style="long"/>', $content);
        self::assertStringContainsString('<number:seconds number:style="long"/></number:date-style>', $content);
    }

    public function testTheHeadingsCanBeLeftOut(): void
    {
        $content = $this->content(Workbook::single('Data', [['x']], [new Column('a')]), new WriteOptions(Headings::None));

        self::assertStringNotContainsString('ce3" office', $content);
        self::assertStringContainsString('<table:table-column/><table:table-row><table:table-cell office:value-type="string"><text:p>x</text:p>', $content);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'plain' => ['a b', '<text:p>a b</text:p>'];
        yield 'escaped' => ['<&>"\'', '<text:p>&lt;&amp;&gt;&quot;&apos;</text:p>'];
        yield 'lines' => ["one\ntwo\r\nthree\rfour", '<text:p>one</text:p><text:p>two</text:p><text:p>three</text:p><text:p>four</text:p>'];
        yield 'tab' => ["a\tb", '<text:p>a<text:tab/>b</text:p>'];
        yield 'leading space' => [' a', '<text:p><text:s/>a</text:p>'];
        yield 'trailing spaces' => ['a  ', '<text:p>a<text:s text:c="2"/></text:p>'];
        yield 'inner spaces' => ['a   b', '<text:p>a <text:s text:c="2"/>b</text:p>'];
        yield 'space around a tab' => ["a \t b", '<text:p>a<text:s/><text:tab/><text:s/>b</text:p>'];
        yield 'empty line' => ["a\n\nb", '<text:p>a</text:p><text:p></text:p><text:p>b</text:p>'];
        yield 'controls' => ["a\x01b\u{FFFE}\u{FFFF}", "<text:p>a\u{FFFD}b\u{FFFD}\u{FFFD}</text:p>"];
        yield 'control and spaces' => ["\x0B  ", "<text:p>\u{FFFD}<text:s text:c=\"2\"/></text:p>"];
    }

    #[DataProvider('texts')]
    public function testATextBecomesParagraphs(string $text, string $xml): void
    {
        self::assertSame($xml, OdsText::paragraphs($text));
    }

    public function testATextThatIsNotUtf8CannotBeWritten(): void
    {
        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('A text to write is not UTF-8.');

        OdsText::paragraphs("caf\xE9");
    }

    public function testTooManyColumnsOrRowsAreRefused(): void
    {
        self::assertStringContainsString('</table:table-row>', $this->content(Workbook::single('Data', [array_fill(0, 16_384, 1)])));

        try {
            $this->content(Workbook::single('Data', [array_fill(0, 16_385, 1)]));
            self::fail('Too many columns.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('An ODS sheet holds at most 16384 columns.', $writeFailed->getMessage());
        }

        self::assertSame(['book.ods'], $this->files());
    }

    public function testTheRowLimitIsHeld(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new OdsWriter($this->folder(), 3)->write(Workbook::single('Data', [[1], [2]], [new Column('a')]), $stream);

        self::assertGreaterThan(0, fstat($stream)['size'] ?? 0);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('An ODS sheet holds at most 3 rows, headings included.');

        new OdsWriter($this->folder(), 3)->write(Workbook::single('Data', [[1], [2], [3]], [new Column('a')]), $stream);
    }

    public function testTheRowLimitLiesWithinTheFormat(): void
    {
        self::assertInstanceOf(OdsWriter::class, new OdsWriter(null, 1));
        self::assertInstanceOf(OdsWriter::class, new OdsWriter(null, 1_048_576));

        foreach ([0, 1_048_577] as $limit) {
            try {
                new OdsWriter(null, $limit);
                self::fail('The limit is refused.');
            } catch (InvalidSpreadsheet $invalidSpreadsheet) {
                self::assertSame('The most rows of a sheet is between 1 and 1048576.', $invalidSpreadsheet->getMessage());
            }
        }
    }

    public function testAStreamThatRefusesTheArchiveFails(): void
    {
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('The stream refused the file.');

        new OdsWriter($this->folder())->write(Workbook::single('Data', [['a']]), $stream);
    }

    public function testItWritesToAnOpenStreamOnlyAndTheSystemDirectoryIsTheDefault(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new OdsWriter()->write(Workbook::single('a', [['x']]), $stream);
        rewind($stream);

        self::assertSame("PK\x03\x04", fread($stream, 4));

        $this->expectException(InvalidSpreadsheet::class);

        new OdsWriter()->write(Workbook::single('a', []), stream_context_create());
    }
}
