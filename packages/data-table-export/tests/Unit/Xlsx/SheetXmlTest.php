<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit\Xlsx;

use DateTimeImmutable;
use Phil\DataTableExport\Cells;
use Phil\DataTableExport\Tests\Unit\Fixture\Level;
use Phil\DataTableExport\Tests\Unit\Fixture\Title;
use Phil\DataTableExport\Xlsx\SheetXml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SheetXml::class)]
#[CoversClass(Cells::class)]
final class SheetXmlTest extends TestCase
{
    public function testItNamesTheColumnsAsASpreadsheetDoes(): void
    {
        self::assertSame(['A', 'B', 'Z', 'AA', 'AZ', 'BA', 'ZZ', 'AAA', 'XFD'], array_map(SheetXml::column(...), [1, 2, 26, 27, 52, 53, 702, 703, 16384]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function cells(): iterable
    {
        yield 'nothing' => [null, ''];
        yield 'an empty text' => ['', ''];
        yield 'true' => [true, '<c r="B3" t="b"><v>1</v></c>'];
        yield 'false' => [false, '<c r="B3" t="b"><v>0</v></c>'];
        yield 'an integer' => [-42, '<c r="B3"><v>-42</v></c>'];
        yield 'a float' => [3.25, '<c r="B3"><v>3.25</v></c>'];
        yield 'infinity' => [\INF, '<c r="B3" t="inlineStr"><is><t xml:space="preserve">INF</t></is></c>'];
        yield 'a text' => [' <b>&"x"</b> ', '<c r="B3" t="inlineStr"><is><t xml:space="preserve"> &lt;b&gt;&amp;&quot;x&quot;&lt;/b&gt; </t></is></c>'];
        yield 'a formula stays text' => ['=1+1', '<c r="B3" t="inlineStr"><is><t xml:space="preserve">=1+1</t></is></c>'];
        yield 'an enum' => [Level::High, '<c r="B3" t="inlineStr"><is><t xml:space="preserve">high</t></is></c>'];
        yield 'a Stringable' => [new Title('Dune'), '<c r="B3" t="inlineStr"><is><t xml:space="preserve">Dune</t></is></c>'];
        yield 'a date' => [new DateTimeImmutable('2026-11-12 00:00 UTC'), '<c r="B3" s="2"><v>46338</v></c>'];
        yield 'a date and time' => [new DateTimeImmutable('2026-11-12 18:00 UTC'), '<c r="B3" s="3"><v>46338.75</v></c>'];
        yield 'the wall clock of its zone' => [new DateTimeImmutable('2026-11-12 06:00 -05:00'), '<c r="B3" s="3"><v>46338.25</v></c>'];
        yield 'one second' => [new DateTimeImmutable('2026-11-12 00:00:01 UTC'), '<c r="B3" s="3"><v>46338.0000115741</v></c>'];
        yield 'before 1970' => [new DateTimeImmutable('1969-12-31 12:00 UTC'), '<c r="B3" s="3"><v>25568.5</v></c>'];
        yield 'the first day counted right' => [new DateTimeImmutable('1900-03-01 00:00 UTC'), '<c r="B3" s="2"><v>61</v></c>'];
        yield 'a day counted wrong' => [new DateTimeImmutable('1900-02-28 06:00 UTC'), '<c r="B3" t="inlineStr"><is><t xml:space="preserve">1900-02-28 06:00:00</t></is></c>'];
    }

    #[DataProvider('cells')]
    public function testItWritesEachKindOfCell(mixed $cell, string $xml): void
    {
        self::assertSame($xml, SheetXml::cell('B3', $cell));
    }

    public function testAStyleIsGivenToEveryCellButADate(): void
    {
        self::assertSame('<c r="A1" t="inlineStr" s="1"><is><t xml:space="preserve">Title</t></is></c>', SheetXml::cell('A1', 'Title', SheetXml::HEADING_STYLE));
        self::assertSame('<c r="A1" s="1"><v>2</v></c>', SheetXml::cell('A1', 2, SheetXml::HEADING_STYLE));
        self::assertSame('<c r="A1" s="1"><v>2.5</v></c>', SheetXml::cell('A1', 2.5, SheetXml::HEADING_STYLE));
        self::assertSame('<c r="A1" t="b" s="1"><v>1</v></c>', SheetXml::cell('A1', true, SheetXml::HEADING_STYLE));
    }

    public function testATextKeepsOnlyWhatXmlHolds(): void
    {
        self::assertSame('ab&apos;c'."\t\n\r".'é', SheetXml::text("a\x00b\x01'c\t\n\r\u{FFFE}é"));
        self::assertSame('a?b', SheetXml::text("a\xFFb"));
    }

    public function testATextIsCutAtTheLimitOfACell(): void
    {
        $long = str_repeat('é', SheetXml::CELL_LIMIT).'x';

        self::assertSame('<c r="A1" t="inlineStr"><is><t xml:space="preserve">'.str_repeat('é', SheetXml::CELL_LIMIT).'</t></is></c>', SheetXml::cell('A1', $long));
        self::assertSame('<c r="A1" t="inlineStr"><is><t xml:space="preserve">'.str_repeat('é', SheetXml::CELL_LIMIT).'</t></is></c>', SheetXml::cell('A1', str_repeat('é', SheetXml::CELL_LIMIT)));
    }
}
