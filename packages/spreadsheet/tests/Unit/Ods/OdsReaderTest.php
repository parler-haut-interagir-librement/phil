<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Ods;

use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\Internal\BudgetFilter;
use Phil\Spreadsheet\Internal\ByteBudget;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Ods\OdsContentParser;
use Phil\Spreadsheet\Ods\OdsReader;
use Phil\Spreadsheet\Ods\OdsTableParser;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OdsReader::class)]
#[CoversClass(OdsContentParser::class)]
#[CoversClass(OdsTableParser::class)]
#[CoversClass(Archive::class)]
#[CoversClass(SafeXmlReader::class)]
#[CoversClass(BudgetFilter::class)]
#[CoversClass(ByteBudget::class)]
#[CoversClass(RowBuilder::class)]
#[CoversClass(Values::class)]
final class OdsReaderTest extends TestCase
{
    use TemporaryFiles;

    private function ods(string $tables, ?string $mimetype = 'application/vnd.oasis.opendocument.spreadsheet'): string
    {
        return $this->zip('book.ods', [...(null === $mimetype ? [] : ['mimetype' => $mimetype]), 'content.xml' => Workbooks::odsContent($tables)]);
    }

    /**
     * @return array<int, list<string>>
     */
    private function read(string $rows, ?string $sheet = null, ReadLimits $limits = new ReadLimits()): array
    {
        return Workbooks::dump(new OdsReader($limits)->rows($this->ods('<table:table table:name="First">'.$rows.'</table:table><table:table table:name="Second"><table:table-row><table:table-cell office:value-type="string"><text:p>second</text:p></table:table-cell></table:table-row></table:table>'), $sheet));
    }

    public function testItReadsTheSheetsInTheirOrder(): void
    {
        $path = $this->ods('<table:table table:name="One"><table:table-row><table:table-cell><table:table table:name="Inner"/></table:table-cell></table:table-row></table:table><table:table table:name="Two"/><table:table table:name="Three"></table:table>');

        self::assertSame(Format::Ods, new OdsReader()->format());
        self::assertSame(['One', 'Two', 'Three'], new OdsReader()->sheetNames($path));
        self::assertSame([], Workbooks::dump(new OdsReader()->rows($path, 'Two')));
        self::assertSame([], Workbooks::dump(new OdsReader()->rows($path, 'Three')));
        self::assertSame([1 => ['text:second']], $this->read('', 'Second'));
    }

    public function testEachCellIsReadWithItsType(): void
    {
        $rows = $this->read(
            '<table:table-column/><table:table-row>'
            .'<table:table-cell office:value-type="string"><text:p>00123</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="string" office:string-value="attribute"><text:p>shown</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="float" office:value="12"><text:p>12</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="percentage" office:value="0.5"/>'
            .'<table:table-cell office:value-type="currency" office:value="-3.25"/>'
            .'<table:table-cell office:value-type="boolean" office:boolean-value="true"/>'
            .'<table:table-cell office:value-type="boolean" office:boolean-value="false"/>'
            .'<table:table-cell office:value-type="boolean" office:boolean-value="1"/>'
            .'<table:table-cell office:value-type="date" office:date-value="2026-11-21"/>'
            .'<table:table-cell office:value-type="date" office:date-value="2026-11-21T14:30:05.25"/>'
            .'<table:table-cell office:value-type="time" office:time-value="PT14H30M00S"><text:p>14:30</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="time" office:time-value="PT01H"/>'
            .'<table:table-cell><text:p>untyped</text:p></table:table-cell>'
            .'<table:table-cell office:value-type="void"/>'
            .'<table:covered-table-cell office:value-type="float" office:value="1"/>'
            .'</table:table-row>',
        );

        self::assertSame([1 => [
            'text:00123',
            'text:attribute',
            'number:12',
            'number:0.5',
            'number:-3.25',
            'boolean:true',
            'boolean:false',
            'boolean:true',
            'date:2026-11-21T00:00:00.000+00:00',
            'date-time:2026-11-21T14:30:05.250+00:00',
            'text:14:30',
            'text:PT01H',
            'text:untyped',
            'empty:',
            'number:1',
        ]], $rows);
    }

    public function testTheTextOfACellKeepsItsSpacesTabsAndLines(): void
    {
        $rows = $this->read(
            '<table:table-row><table:table-cell office:value-type="string">'
            .'<text:p>a <text:span>b</text:span><text:s/>c<text:s text:c="3"/>d</text:p>'
            .'<text:p/>'
            .'<text:p>e<text:tab/>f<text:line-break/>g  '."\n\t".'h</text:p>'
            .'<office:annotation><text:p>note</text:p></office:annotation>'
            .'<draw:frame><text:p>shape</text:p></draw:frame><draw:line/>'
            .'<text:h>title<text:s text:c="0"/></text:h>'
            .'</table:table-cell>'
            .'<table:table-cell office:value-type="string"><text:s/><text:tab/><text:line-break/><text:p><text:a>link</text:a><text:bookmark/></text:p></table:table-cell>'
            .'<table:table-cell office:value-type="string"><text:p><![CDATA[x  y]]></text:p></table:table-cell>'
            .'</table:table-row>',
        );

        self::assertSame([1 => ["text:a b c   d\n\ne\tf\ng h\ntitle ", 'text:link', 'text:x y']], $rows);
    }

    public function testRepeatedCellsAndRowsAreReadWithoutFillingMemory(): void
    {
        $rows = $this->read(
            '<table:table-header-rows><table:table-row><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="3"/><table:table-cell table:number-columns-repeated="16000"/><table:table-cell office:value-type="float" office:value="2"/><table:table-cell table:number-columns-repeated="1000000"/></table:table-row></table:table-header-rows>'
            .'<table:table-row table:number-rows-repeated="2"><table:table-cell table:number-columns-repeated="2"/><table:table-cell office:value-type="string"><text:p>x</text:p></table:table-cell></table:table-row>'
            .'<table:table-row table:number-rows-repeated="3"><table:table-cell table:number-columns-repeated="1024"/></table:table-row>'
            .'<table:table-row table:number-rows-repeated="2"/>'
            .'<table:table-row><table:covered-table-cell/><table:table-cell office:value-type="string"><text:p>y</text:p></table:table-cell></table:table-row>'
            .'<table:table-row table:number-rows-repeated="1048000"><table:table-cell/></table:table-row>',
            limits: new ReadLimits(maxRows: 9),
        );

        self::assertSame([
            1 => ['number:1', 'number:1', 'number:1', ...array_fill(0, 16000, 'empty:'), 'number:2'],
            2 => ['empty:', 'empty:', 'text:x'],
            3 => ['empty:', 'empty:', 'text:x'],
            9 => ['empty:', 'text:y'],
        ], $rows);
    }

    public function testTheLimitsOfColumnsRowsAndCellsHold(): void
    {
        self::assertCount(1, $this->read('<table:table-row><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="2"/></table:table-row>', limits: new ReadLimits(maxColumns: 2)));

        try {
            $this->read('<table:table-row><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="3"/></table:table-row>', limits: new ReadLimits(maxColumns: 2));
            self::fail('Three columns.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The number of columns of a row goes over the limit of 2.', $readRefused->getMessage());
        }

        try {
            $this->read('<table:table-row><table:table-cell table:number-columns-repeated="2"/><table:table-cell office:value-type="float" office:value="1"/></table:table-row>', limits: new ReadLimits(maxColumns: 2));
            self::fail('The third column.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The number of columns of a row goes over the limit of 2.', $readRefused->getMessage());
        }

        self::assertCount(2, $this->read('<table:table-row table:number-rows-repeated="2"><table:table-cell office:value-type="float" office:value="1"/></table:table-row>', limits: new ReadLimits(maxRows: 2)));

        try {
            $this->read('<table:table-row table:number-rows-repeated="3"><table:table-cell office:value-type="float" office:value="1"/></table:table-row>', limits: new ReadLimits(maxRows: 2));
            self::fail('The third row.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The number of the row 3 goes over the limit of 2.', $readRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The number of cells read goes over the limit of 5.');

        $this->read('<table:table-row table:number-rows-repeated="3"><table:table-cell office:value-type="float" office:value="1" table:number-columns-repeated="2"/></table:table-row>', limits: new ReadLimits(maxCells: 5));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'row repeat' => ['<table:table-row table:number-rows-repeated="0"/>', 'repeats a row or a cell a number of times that is not one'];
        yield 'cell repeat' => ['<table:table-row><table:table-cell table:number-columns-repeated="x"/></table:table-row>', 'repeats a row or a cell a number of times that is not one'];
        yield 'number' => ['<table:table-row><table:table-cell office:value-type="float" office:value="1,5"/></table:table-row>', 'has a number cell that holds no number'];
        yield 'no number' => ['<table:table-row><table:table-cell office:value-type="float"/></table:table-row>', 'has a number cell that holds no number'];
        yield 'date' => ['<table:table-row><table:table-cell office:value-type="date" office:date-value="2026-02-30"/></table:table-row>', 'has a date cell that holds no date'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedTableIsRefused(string $rows, string $reason): void
    {
        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The part "content.xml" '.$reason.'.');

        $this->read($rows);
    }

    public function testAMissingSheetIsRefused(): void
    {
        try {
            $this->read('', 'Third');
            self::fail('No such sheet.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The spreadsheet has no sheet "Third".', $readRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The spreadsheet has no sheet.');

        iterator_to_array(new OdsReader()->rows($this->ods('')));
    }

    public function testTheMediaTypeIsCheckedWhenThereIsOne(): void
    {
        self::assertSame(['T'], new OdsReader()->sheetNames($this->ods('<table:table table:name="T"/>', null)));
        self::assertSame(['T'], new OdsReader()->sheetNames($this->ods('<table:table table:name="T"/>', 'application/vnd.oasis.opendocument.spreadsheet-template')));

        try {
            new OdsReader()->sheetNames($this->ods('<table:table table:name="T"/>', 'application/vnd.oasis.opendocument.text'));
            self::fail('A text document.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The archive is an OpenDocument file, but not a spreadsheet.', $readRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The size of the part "mimetype" goes over the limit of 100.');

        new OdsReader()->sheetNames($this->ods('', str_repeat('x', 101)));
    }

    public function testADocumentTypeIsRefused(): void
    {
        $path = $this->zip('xxe.ods', ['content.xml' => '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;">]><office:document-content '.Workbooks::ODS_NAMESPACES.'/>']);

        $this->expectExceptionMessageIsOrContains('The part "content.xml" declares a document type, which a spreadsheet never needs.');

        new OdsReader()->sheetNames($path);
    }

    public function testAnArchiveWithoutContentIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The archive has no part "content.xml".');

        iterator_to_array(new OdsReader()->rows($this->zip('empty.ods', ['mimetype' => 'application/vnd.oasis.opendocument.spreadsheet'])));
    }

    public function testReadingStopsAtTheEndOfTheTable(): void
    {
        // What follows the table is never read: a part cut after it still gives the table's rows.
        $path = $this->zip('cut.ods', ['content.xml' => '<office:document-content '.Workbooks::ODS_NAMESPACES.'><office:body><office:spreadsheet><table:table table:name="A"><table:table-row><table:table-cell office:value-type="string"><text:p>a</text:p></table:table-cell></table:table-row></table:table><table:table']);

        self::assertSame([1 => ['text:a']], Workbooks::dump(new OdsReader()->rows($path)));
    }
}
