<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Output;

use DateTimeImmutable;
use DOMDocument;
use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Book\Page;
use Phil\BrailleExport\Book\Section;
use Phil\BrailleExport\Book\Volume;
use Phil\BrailleExport\Exception\UnrepresentableCell;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Output\PefMetadata;
use Phil\BrailleExport\Output\PefWriter;
use Phil\BrailleExport\Output\UnicodeWriter;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrfWriter::class)]
#[CoversClass(PefWriter::class)]
#[CoversClass(PefMetadata::class)]
#[CoversClass(UnicodeWriter::class)]
#[CoversClass(UnrepresentableCell::class)]
final class WritersTest extends TestCase
{
    public function testBrfWritesEachCellAsItsAsciiCharacter(): void
    {
        $every = '';

        for ($mask = 0; $mask < 64; ++$mask) {
            $every .= mb_chr(0x2800 + $mask, 'UTF-8');
        }

        $brf = new BrfWriter()->write($this->book([[[[$every, ''], [Ascii::braille('AB')]]]]));

        self::assertSame(' A1B\'K2L@CIF/MSP"E3H9O6R^DJG>NTQ,*5<-U8V.%[$+X!&;:4\\0Z7(_?W]#Y)='."\r\n\r\n\fAB\r\n\f", $brf);
    }

    public function testBrfRefusesEightDotCells(): void
    {
        $this->expectException(UnrepresentableCell::class);
        $this->expectExceptionMessageIsOrContains('Row 2 of braille page 2 holds a cell with dot 7 or 8, which BRF cannot write.');

        new BrfWriter()->write($this->book([[[[Ascii::braille('A')]]], [[[Ascii::braille('A'), Ascii::braille('A')."\u{2840}"]]]]));
    }

    public function testBrfRefusesTheHighestCell(): void
    {
        $this->expectException(UnrepresentableCell::class);

        new BrfWriter()->write($this->book([[[["\u{28FF}"]]]]));
    }

    public function testBrfOfAnEmptyPage(): void
    {
        self::assertSame("\f", new BrfWriter()->write($this->book([[[[]]]])));
    }

    public function testUnicodeGivesThePagesForAScreen(): void
    {
        $book = $this->book([[[[Ascii::braille('A'), '', Ascii::braille('B')], [Ascii::braille('C')]]]]);

        self::assertSame(["\u{2801}\n\n\u{2803}", "\u{2809}"], new UnicodeWriter()->pages($book));
        self::assertSame("\u{2801}\n\n\u{2803}\n\n\u{2809}", new UnicodeWriter()->write($book));
        self::assertSame("\u{2801}\n\n\u{2803}\f\u{2809}", new UnicodeWriter()->write($book, "\f"));
    }

    public function testPefKeepsTheVolumesSectionsPagesAndRows(): void
    {
        $book = new Book(
            [
                new Volume([new Section([new Page([Ascii::braille('A'), ''], 1)]), new Section([new Page([], 2)])]),
                new Volume([new Section([new Page([Ascii::braille('B')], 3)])]),
            ],
            new PageFormat(30, 20, duplex: true),
            'fr-CA',
            'Le <petit> & "grand"'."\x01",
        );

        $pef = new PefWriter()->write($book, new PefMetadata('isbn:123', 'Ann', 'House', new DateTimeImmutable('2026-11-13 10:00')));

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<pef version="2008-1" xmlns="http://www.daisy.org/ns/2008/pef"><head><meta xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:format>application/x-pef+xml</dc:format><dc:identifier>isbn:123</dc:identifier>'
            .'<dc:title>Le &lt;petit&gt; &amp; &quot;grand&quot;</dc:title><dc:creator>Ann</dc:creator><dc:publisher>House</dc:publisher>'
            .'<dc:language>fr-CA</dc:language><dc:date>2026-11-13</dc:date></meta></head><body>'
            .'<volume cols="30" rows="20" rowgap="0" duplex="true"><section><page><row>⠁</row><row/></page></section><section><page><row/></page></section></volume>'
            .'<volume cols="30" rows="20" rowgap="0" duplex="true"><section><page><row>⠃</row></page></section></volume>'
            .'</body></pef>'."\n",
            $pef,
        );

        $xml = new DOMDocument();
        self::assertTrue($xml->loadXML($pef));
    }

    public function testPefDerivesItsIdentifierFromTheContent(): void
    {
        $writer = new PefWriter();
        $one = $writer->write($this->book([[[[Ascii::braille('A')]]]]));
        $same = $writer->write($this->book([[[[Ascii::braille('A')]]]]));
        $other = $writer->write($this->book([[[[Ascii::braille('B')]]]]));

        self::assertSame($one, $same);
        self::assertNotSame($one, $other);
        self::assertMatchesRegularExpression('#<dc:identifier>urn:sha256:[0-9a-f]{64}</dc:identifier><dc:language>en</dc:language></meta>#', $one);
        self::assertStringContainsString('duplex="false"', $one);
        self::assertStringContainsString('cols="40" rows="25"', $one);
    }

    public function testPefMetadataDefaults(): void
    {
        $metadata = new PefMetadata();

        self::assertNull($metadata->identifier);
        self::assertSame('', $metadata->creator);
        self::assertSame('', $metadata->publisher);
        self::assertNull($metadata->date);
    }

    /**
     * @param list<list<list<list<string>>>> $volumes each volume, its sections, their pages, their rows
     */
    private function book(array $volumes): Book
    {
        $number = 0;

        return new Book(
            array_map(static function (array $sections) use (&$number): Volume {
                return new Volume(array_map(static function (array $pages) use (&$number): Section {
                    return new Section(array_map(static function (array $rows) use (&$number): Page {
                        return new Page($rows, ++$number);
                    }, $pages));
                }, $sections));
            }, $volumes),
            new PageFormat(),
            'en',
        );
    }
}
