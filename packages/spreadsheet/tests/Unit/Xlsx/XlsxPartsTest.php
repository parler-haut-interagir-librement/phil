<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Xlsx;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Xlsx\CellReference;
use Phil\Spreadsheet\Xlsx\ExcelDate;
use Phil\Spreadsheet\Xlsx\NumberFormats;
use Phil\Spreadsheet\Xlsx\XlsxText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CellReference::class)]
#[CoversClass(ExcelDate::class)]
#[CoversClass(NumberFormats::class)]
#[CoversClass(XlsxText::class)]
#[CoversClass(Values::class)]
final class XlsxPartsTest extends TestCase
{
    public function testColumnLettersGoBothWays(): void
    {
        foreach ([1 => 'A', 26 => 'Z', 27 => 'AA', 52 => 'AZ', 53 => 'BA', 702 => 'ZZ', 703 => 'AAA', 16384 => 'XFD'] as $column => $letters) {
            self::assertSame($letters, CellReference::letters($column));
            self::assertSame($column, CellReference::column($letters.'12'));
        }

        self::assertSame('', CellReference::letters(0));
        self::assertSame(2, CellReference::column('b'));
        self::assertNull(CellReference::column('12'));
        self::assertNull(CellReference::column('AAAA1'));
        self::assertNull(CellReference::column('A1B'));
        self::assertNull(CellReference::column(' A1'));
    }

    /**
     * @return iterable<array{string, int|float}>
     */
    public static function serials(): iterable
    {
        yield ['1900-03-01T00:00:00', 61];
        yield ['2026-11-21T00:00:00', 46347];
        yield ['2026-11-21T12:00:00', 46347.5];
        yield ['2026-11-21T06:00:00', 46347.25];
        yield ['9999-12-31T00:00:00', 2958465];
    }

    #[DataProvider('serials')]
    public function testADateIsItsSerialNumberAndBack(string $date, int|float $serial): void
    {
        $written = ExcelDate::serial(new DateTimeImmutable($date, new DateTimeZone('Asia/Tokyo')));

        self::assertSame($serial, $written);
        self::assertSame($date.'.000+00:00', ExcelDate::date((float) $serial)?->format('Y-m-d\TH:i:s.vP'));
    }

    public function testTheTimeKeepsItsMilliseconds(): void
    {
        $serial = ExcelDate::serial(new DateTimeImmutable('2026-11-21 14:30:05.123'));

        self::assertIsFloat($serial);
        self::assertSame('2026-11-21 14:30:05.123', ExcelDate::date($serial)?->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-11-22 00:00:00.000', ExcelDate::date(46347.9999999999)?->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-11-21 00:00:00.001', ExcelDate::date(46347 + 0.0014 / 86400)?->format('Y-m-d H:i:s.v'));
    }

    public function testTheDaysBeforeMarch1900AreOneDayOffAndThe1904SystemStartsIn1904(): void
    {
        self::assertSame('1900-01-01', ExcelDate::date(1)?->format('Y-m-d'));
        self::assertSame('1900-02-28', ExcelDate::date(59)?->format('Y-m-d'));
        self::assertSame('1900-03-01', ExcelDate::date(60)?->format('Y-m-d'));
        self::assertSame('1899-12-30 12:00', ExcelDate::date(0.5)?->format('Y-m-d H:i'));
        self::assertSame('1904-01-01', ExcelDate::date(0, true)?->format('Y-m-d'));
        self::assertSame('1904-01-02', ExcelDate::date(1, true)?->format('Y-m-d'));
        self::assertSame('9999-12-31 23:59', ExcelDate::date(2958465.9999)?->format('Y-m-d H:i'));
        self::assertNull(ExcelDate::date(-0.5));
        self::assertNull(ExcelDate::date(2958466));
        self::assertNull(ExcelDate::date(2958465 - 1462 + 1, true));
    }

    public function testADateBeforeMarch1900CannotBeWritten(): void
    {
        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('An XLSX workbook writes the dates from 1900-03-01 to 9999-12-31, not 1900-02-28.');

        ExcelDate::serial(new DateTimeImmutable('1900-02-28 23:59:59'));
    }

    public function testADateAfter9999CannotBeWritten(): void
    {
        $this->expectExceptionMessageIsOrContains('not 10000-01-01.');

        ExcelDate::serial(new DateTimeImmutable('9999-12-31')->modify('+1 day'));
    }

    /**
     * @return iterable<array{int, string|null, CellType|null}>
     */
    public static function formats(): iterable
    {
        foreach ([14, 15, 16, 17, 27, 28, 29, 30, 31, 34, 35, 36, 50, 51, 52, 53, 54, 57, 58] as $id) {
            yield 'day '.$id => [$id, null, CellType::Date];
        }

        foreach ([18, 19, 20, 21, 22, 32, 33, 45, 46, 47, 55, 56] as $id) {
            yield 'time '.$id => [$id, null, CellType::DateTime];
        }

        foreach ([0, 1, 2, 9, 10, 11, 12, 13, 23, 37, 48, 49] as $id) {
            yield 'number '.$id => [$id, null, null];
        }

        yield 'iso day' => [164, 'yyyy-mm-dd', CellType::Date];
        yield 'day month' => [164, 'DD/MM/YYYY', CellType::Date];
        yield 'month' => [164, 'mmm', CellType::Date];
        yield 'time' => [164, 'hh:mm', CellType::DateTime];
        yield 'seconds' => [164, 'mm:ss', CellType::DateTime];
        yield 'am pm' => [164, 'AM/PM', CellType::DateTime];
        yield 'a p' => [164, 'A/P', CellType::DateTime];
        yield 'general' => [164, 'General', null];
        yield 'decimals' => [164, '0.00', null];
        yield 'scientific' => [164, '0.00E+00', null];
        yield 'quoted' => [164, '0" days"', null];
        yield 'escaped' => [164, '0\d', null];
        yield 'colour' => [164, '[Red]0', null];
        yield 'locale' => [164, '[$-409]0', null];
        yield 'padding' => [164, '0_)', null];
        yield 'fill' => [164, '0*-', null];
        yield 'negative part' => [164, '0;[Red]yyyy', null];
        yield 'text' => [164, '@', null];
    }

    #[DataProvider('formats')]
    public function testAFormatShowsADayADateAndTimeOrANumber(int $id, ?string $code, ?CellType $kind): void
    {
        self::assertSame($kind, NumberFormats::kind($id, $code));
    }

    public function testATextIsEscapedForXmlAndTheFormat(): void
    {
        self::assertSame('a &lt;b&gt; &amp; &quot;c&quot; &apos;d&apos;', XlsxText::escape('a <b> & "c" \'d\''));
        self::assertSame("tab\tline\nreturn\r", XlsxText::escape("tab\tline\nreturn\r"));
        self::assertSame('_x0000__x0001__x001F__x000B_', XlsxText::escape("\x00\x01\x1F\x0B"));
        self::assertSame('_xFFFE_x_xFFFF_', XlsxText::escape("\u{FFFE}x\u{FFFF}"));
        self::assertSame('_x005F_x0041_ _x', XlsxText::escape('_x0041_ _x'));
        self::assertSame('', XlsxText::escape(''));
    }

    public function testATextThatIsNotUtf8CannotBeWritten(): void
    {
        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('A text to write is not UTF-8.');

        XlsxText::escape("caf\xE9");
    }

    public function testATextIsUnescaped(): void
    {
        foreach (["\x00\x01\x1F\x0B", "\u{FFFE}x\u{FFFF}", '_x0041_ _x', 'plain'] as $text) {
            self::assertSame($text, XlsxText::unescape(html_entity_decode(XlsxText::escape($text), \ENT_XML1 | \ENT_QUOTES)));
        }

        self::assertSame('Aé', XlsxText::unescape('_x0041__x00e9_'));
        self::assertSame('_xD800__xdfff_', XlsxText::unescape('_xD800__xdfff_'));
        self::assertSame("\u{D7FF}\u{E000}", XlsxText::unescape('_xD7FF__xE000_'));
        self::assertSame('_x41_', XlsxText::unescape('_x41_'));
    }

    public function testATextFitsInItsCharacters(): void
    {
        self::assertTrue(XlsxText::fits(str_repeat('a', 32767)));
        self::assertTrue(XlsxText::fits(str_repeat('é', 32767)));
        self::assertFalse(XlsxText::fits(str_repeat('a', 32768)));
        self::assertFalse(XlsxText::fits(str_repeat('é', 32768)));
    }
}
