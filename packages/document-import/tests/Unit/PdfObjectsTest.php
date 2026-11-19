<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Codepage;
use Phil\DocumentImport\Internal\Pdf\GlyphNames;
use Phil\DocumentImport\Internal\Pdf\PdfCMap;
use Phil\DocumentImport\Internal\Pdf\PdfDictionary;
use Phil\DocumentImport\Internal\Pdf\PdfFilters;
use Phil\DocumentImport\Internal\Pdf\PdfKeyword;
use Phil\DocumentImport\Internal\Pdf\PdfName;
use Phil\DocumentImport\Internal\Pdf\PdfParser;
use Phil\DocumentImport\Internal\Pdf\PdfReference;
use Phil\DocumentImport\Internal\Pdf\PdfStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfParser::class)]
#[CoversClass(PdfFilters::class)]
#[CoversClass(PdfCMap::class)]
#[CoversClass(GlyphNames::class)]
#[CoversClass(Codepage::class)]
#[CoversClass(PdfName::class)]
#[CoversClass(PdfReference::class)]
#[CoversClass(PdfDictionary::class)]
#[CoversClass(PdfKeyword::class)]
#[CoversClass(PdfStream::class)]
final class PdfObjectsTest extends TestCase
{
    public function testValuesAreRead(): void
    {
        $parser = new PdfParser('<< /Type /Page /Kids [1 0 R 2 0 R] /N -3 /F .5 /B true /X false /Z null /S (a\\(b\\)\\n\\101\\\\) /H <48 6 > /Odd#20Name 1 /R 7 0 R >> % comment');
        $value = $parser->value();

        self::assertInstanceOf(PdfDictionary::class, $value);
        self::assertSame('Page', $value->name('Type'));
        self::assertNull($value->name('N'));
        self::assertEquals([new PdfReference(1), new PdfReference(2)], $value->get('Kids'));
        self::assertSame(-3, $value->get('N'));
        self::assertSame(0.5, $value->get('F'));
        self::assertTrue($value->get('B'));
        self::assertFalse($value->get('X'));
        self::assertNull($value->get('Z'));
        self::assertSame("a(b)\nA\\", $value->get('S'));
        self::assertSame('H`', $value->get('H'));
        self::assertSame(1, $value->get('Odd Name'));
        self::assertEquals(new PdfReference(7), $value->get('R'));
        self::assertNull($parser->value());
    }

    public function testStringsKeepTheirNestingAndEscapes(): void
    {
        self::assertSame('a (nested) b', new PdfParser('(a (nested) b)')->value());
        self::assertSame("\r\t\x08\fxy", new PdfParser("(\\r\\t\\b\\fx\\\ny)")->value());
        self::assertSame('xy', new PdfParser("(x\\\r\ny)")->value());
        self::assertSame('xy', new PdfParser("(x\\\ry)")->value());
        self::assertSame("\x07".'8', new PdfParser('(\\78)')->value());
        self::assertSame("\xFF", new PdfParser('(\\777)')->value());
        self::assertSame('open', new PdfParser('(open')->value());
        self::assertSame('', new PdfParser('<>')->value());
        self::assertSame("\xAB\xC0", new PdfParser('<ABC')->value());
    }

    public function testDamagedNumbersReadAsZero(): void
    {
        self::assertSame(0, new PdfParser('--5')->value());
        self::assertSame(0, new PdfParser('.')->value());
        self::assertSame(1.0, new PdfParser('1.')->value());
        self::assertSame(12, new PdfParser('+12')->value());
    }

    public function testOperationsCarryTheirOperandsAndSkipInlineImages(): void
    {
        $operations = iterator_to_array(new PdfParser("1 0 0 1 72 720 Tm (Hi) Tj BI /W 1 ID \x00EI\x01 EI Q [(a) -20 (b)] TJ ) ] 5")->operations(), false);

        self::assertSame(['Tm', 'Tj', 'BI', 'ID', 'EI', 'Q', 'TJ', ')', ']'], array_map(static fn (array $operation): string => $operation[0], $operations));
        self::assertSame([1, 0, 0, 1, 72, 720], $operations[0][1]);
        self::assertSame([['a', -20, 'b']], $operations[6][1]);
        self::assertSame([], iterator_to_array(new PdfParser('BI ID never ends')->operations(), false)[1][1] ?? []);
    }

    public function testArraysOfReferencesAndNesting(): void
    {
        self::assertEquals([1, new PdfReference(2, 5), new PdfKeyword('x')], new PdfParser('[1 2 5 R x')->value());

        $this->expectExceptionObject(LimitExceeded::because('The PDF nests arrays and dictionaries deeper than 2.'));
        new PdfParser('[[[[1]]]]', 0, 2)->value();
    }

    public function testFlateAndItsPredictorsAreUndone(): void
    {
        $filters = new PdfFilters(1000, 10_000);
        $rows = "\x02\x01\x01\x01\x02\x00\x01\x01\x01\x02\x01\x01\x01\x01\x01\x01\x03\x02\x02\x02\x04\x01\x01\x01";

        self::assertSame(['hello', null], $filters->decode((string) gzcompress('hello'), new PdfName('FlateDecode'), null));
        self::assertSame([$this->png($rows, 3), null], $filters->decode((string) gzcompress($rows), [new PdfName('Fl')], [new PdfDictionary(['Predictor' => 12, 'Columns' => 3, 'Colors' => 1])]));
        self::assertSame(["\x01\x03\x06\x0A", null], $filters->decode((string) gzcompress("\x01\x02\x03\x04"), new PdfName('FlateDecode'), new PdfDictionary(['Predictor' => 2, 'Columns' => 4])));
        self::assertSame(["\x01\x02", null], $filters->decode((string) gzcompress("\x01\x02"), new PdfName('FlateDecode'), new PdfDictionary(['Predictor' => 1])));
        self::assertSame(['', null], $filters->decode('not flate', new PdfName('FlateDecode'), null));
        $text = implode(' ', range(1, 2000));
        [$damaged] = new PdfFilters(100_000, 100_000)->decode(substr((string) gzcompress($text), 0, -40).str_repeat('x', 40), new PdfName('FlateDecode'), null);
        self::assertSame(substr($text, 0, 5000), substr($damaged, 0, 5000));
    }

    public function testTheOtherFiltersAreUndone(): void
    {
        $filters = new PdfFilters(1000, 10_000);

        self::assertSame(['Hello', null], $filters->decode('48656C6C6F>', new PdfName('ASCIIHexDecode'), null));
        self::assertSame(['Hel', null], $filters->decode('48 65 6C', new PdfName('AHx'), null));
        self::assertSame(["\xA0", null], $filters->decode('A>', new PdfName('AHx'), null));
        self::assertSame(['Hello', null], $filters->decode('<~87cURDZ~>', new PdfName('ASCII85Decode'), null));
        self::assertSame(["\0\0\0\0abc", null], $filters->decode("z@:E^\n~>", new PdfName('A85'), null));
        self::assertSame(['abcAAAx', null], $filters->decode("\x02abc\xFEA\x00x\x80ignored", new PdfName('RunLengthDecode'), null));
        self::assertSame(['', null], $filters->decode("\x80", new PdfName('RL'), null));
        self::assertSame(['-----A---B', null], $filters->decode("\x80\x0B\x60\x50\x22\x0C\x0C\x85\x01", new PdfName('LZWDecode'), null));
        self::assertSame(['ab', 'DCTDecode'], $filters->decode('ab', [new PdfName('DCTDecode')], null));
        self::assertSame(['ab', null], $filters->decode('ab', [12, null], null));
    }

    public function testDecodingIsBounded(): void
    {
        $filters = new PdfFilters(100, 150);
        $filters->decode((string) gzcompress(str_repeat('a', 100)), new PdfName('FlateDecode'), null);

        try {
            $filters->decode((string) gzcompress(str_repeat('a', 101)), new PdfName('FlateDecode'), null);
            self::fail('A stream larger than the limit was decoded.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('A stream of the PDF is larger than 100 bytes once decoded.', $limitExceeded->getMessage());
        }

        $this->expectExceptionObject(LimitExceeded::because('The streams of the PDF are larger than 150 bytes once decoded.'));
        $filters->decode(str_repeat('61', 60), new PdfName('AHx'), null);
    }

    public function testACMapMapsCodesCharactersAndRanges(): void
    {
        $cmap = new PdfCMap("begincodespacerange <00> <7F> <8000> <FFFF> endcodespacerange\n"
            ."beginbfchar <41> <0058> <42> <D835DC00> <43> <> endbfchar\n"
            .'beginbfrange <50> <52> <0061> <60> <61> [<0031> <00320033>] <70> <6F> <0041> <8000> <8001> <0066006A> endbfrange');

        self::assertSame([1, 2], $cmap->lengths());
        self::assertTrue($cmap->isCode('A'));
        self::assertFalse($cmap->isCode("\x80"));
        self::assertTrue($cmap->isCode("\x80\x01"));
        self::assertSame('X', $cmap->text('A'));
        self::assertSame('𝐀', $cmap->text('B'));
        self::assertSame('', $cmap->text('C'));
        self::assertSame('a', $cmap->text('P'));
        self::assertSame('c', $cmap->text('R'));
        self::assertSame('1', $cmap->text('`'));
        self::assertSame('23', $cmap->text('a'));
        self::assertNull($cmap->text('S'));
        self::assertNull($cmap->text('p'));
        self::assertSame('fk', $cmap->text("\x80\x01"));
        self::assertNull($cmap->text("\x00\x50"));
        self::assertSame([], new PdfCMap('')->lengths());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function glyphs(): iterable
    {
        yield 'named' => ['quoteright', '’'];
        yield 'accented' => ['eacute', 'é'];
        yield 'letter' => ['Q', 'Q'];
        yield 'suffixed' => ['a.sc', 'a'];
        yield 'uni' => ['uni00E9', 'é'];
        yield 'uni pair' => ['uni00410042', 'AB'];
        yield 'u' => ['u1D400', '𝐀'];
        yield 'surrogate' => ['uD800', "\u{FFFD}"];
        yield 'unknown' => ['g123', null];
        yield 'digit name' => ['one', '1'];
        yield 'lowercase uni' => ['uni00e9', null];
    }

    #[DataProvider('glyphs')]
    public function testGlyphNamesHaveTheirText(string $name, ?string $text): void
    {
        self::assertSame($text, GlyphNames::text($name));
    }

    public function testCodePagesAreRead(): void
    {
        self::assertSame('é€', Codepage::decode("\xE9\x80"));
        self::assertSame('é€', Codepage::decode("\xE9\x80", '1252'));
        self::assertSame('Жж', Codepage::decode("\xC6\xE6", '1251'));
        self::assertSame('é€', Codepage::decode("\xE9\x80", '1250'));
        self::assertSame('Ä“ﬁˇ a', Codepage::decode("\x80\xD2\xDE\xFF a", Codepage::MAC_ROMAN));
        self::assertSame("\u{FFFD}", Codepage::character(-1));
        self::assertSame("\u{FFFD}", Codepage::character(0x110000));
        self::assertSame("\u{FFFD}", Codepage::character(0xDFFF));
        self::assertSame("\u{D7FF}", Codepage::character(0xD7FF));
        self::assertSame("\u{E000}", Codepage::character(0xE000));
        self::assertSame("\u{10FFFF}", Codepage::character(0x10FFFF));
        self::assertSame("\0", Codepage::character(0));
    }

    /**
     * Rows predicted by the PNG filters, restored as a reader restores them.
     *
     * @param positive-int $width
     */
    private function png(string $rows, int $width): string
    {
        $output = '';
        $previous = str_repeat("\0", $width);

        foreach (str_split($rows, $width + 1) as $row) {
            $type = \ord($row[0]);
            $line = '';

            for ($index = 0; $index < $width; ++$index) {
                $left = $index > 0 ? \ord($line[$index - 1]) : 0;
                $up = \ord($previous[$index]);
                $corner = $index > 0 ? \ord($previous[$index - 1]) : 0;
                $estimate = $left + $up - $corner;
                $paeth = abs($estimate - $left) <= abs($estimate - $up) && abs($estimate - $left) <= abs($estimate - $corner) ? $left : (abs($estimate - $up) <= abs($estimate - $corner) ? $up : $corner);
                $line .= \chr((\ord($row[$index + 1]) + [0, $left, $up, intdiv($left + $up, 2), $paeth][$type]) & 0xFF);
            }

            $output .= $line;
            $previous = $line;
        }

        return $output;
    }
}
