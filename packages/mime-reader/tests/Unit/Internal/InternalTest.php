<?php

declare(strict_types=1);

namespace Phil\MimeReader\Tests\Unit\Internal;

use Phil\MimeReader\Internal\Bytes;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Internal\TransferDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bytes::class)]
#[CoversClass(Charset::class)]
#[CoversClass(EncodedWords::class)]
#[CoversClass(TransferDecoder::class)]
final class InternalTest extends TestCase
{
    public function testANeedleIsFoundOnlyInsideTheRange(): void
    {
        $source = str_repeat('a', 10_000).'--b'.str_repeat('a', 10_000).'--b';

        self::assertSame(10_000, Bytes::find($source, '--b', 0, \strlen($source)));
        self::assertSame(10_000, Bytes::find($source, '--b', 10_000, 10_003));
        self::assertNull(Bytes::find($source, '--b', 0, 10_002));
        self::assertSame(20_003, Bytes::find($source, '--b', 10_001, \strlen($source)));
        self::assertNull(Bytes::find($source, '--b', 10_001, \strlen($source) - 1));
        self::assertNull(Bytes::find('', '--b', 0, 0));
    }

    public function testANeedleAcrossTwoWindowsIsFound(): void
    {
        foreach ([4094, 4095, 4096, 4097, 12_286, 12_287, 12_288, 1_052_670, 2_101_246] as $offset) {
            $source = str_repeat('a', $offset).'xyz'.str_repeat('a', 10);

            self::assertSame($offset, Bytes::find($source, 'xyz', 0, \strlen($source)), (string) $offset);
        }
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function charsets(): iterable
    {
        yield 'none' => ["caf\xC3\xA9", null, 'café'];
        yield 'empty' => ["caf\xC3\xA9", '', 'café'];
        yield 'utf-8 with spaces and case' => ["caf\xC3\xA9", ' UTF-8 ', 'café'];
        yield 'utf8' => ["caf\xC3\xA9", 'utf8', 'café'];
        yield 'us-ascii read as utf-8' => ["caf\xC3\xA9", 'us-ascii', 'café'];
        yield 'ascii' => ["caf\xC3\xA9", 'ascii', 'café'];
        yield 'invalid utf-8 replaced' => ["caf\xE9", 'utf-8', 'caf?'];
        yield 'latin-1 read as windows-1252' => ["\x93caf\xE9\x94", 'ISO-8859-1', '“café”'];
        yield 'latin1' => ["caf\xE9", 'latin1', 'café'];
        yield 'l1' => ["caf\xE9", 'l1', 'café'];
        yield 'iso8859-1' => ["caf\xE9", 'iso8859-1', 'café'];
        yield 'iso_8859-1' => ["caf\xE9", 'iso_8859-1', 'café'];
        yield 'cp1252' => ["\x80", 'cp1252', '€'];
        yield 'cp1251' => ["\xC0", 'cp1251', 'А'];
        yield 'cp1254' => ["\xD0", 'cp1254', 'Ğ'];
        yield 'koi8-r' => ["\xC1", 'koi8-r', 'а'];
        yield 'gb2312' => ["\xC4\xE3", 'gb2312', '你'];
        yield 'gbk' => ["\xC4\xE3", 'GBK', '你'];
        yield 'ks_c_5601-1987' => ["\xB0\xA1", 'ks_c_5601-1987', '가'];
        yield 'big5' => ["\xA4\xA4", 'big5', '中'];
        yield 'shift_jis' => ["\x82\xA0", 'shift_jis', 'あ'];
        yield 'x-sjis' => ["\x82\xA0", 'x-sjis', 'あ'];
        yield 'utf-7' => ['+AOk-', 'unicode-1-1-utf-7', 'é'];
        yield 'unknown' => ["caf\xC3\xA9", 'x-unknown', 'café'];
        yield 'tis-620 through iconv' => ["\xA1", 'TIS-620', 'ก'];
        yield 'macintosh through iconv' => ["caf\x8E", 'macintosh', 'café'];
        yield 'iconv drops what the set lacks' => ["a\xFFb", 'tis-620', 'ab'];
        yield 'iconv options refused' => ["\xA1", 'tis-620//ignore', '?'];
        yield 'base64 is no charset' => ['Y2Fm', 'base64', 'Y2Fm'];
        yield 'uuencode is no charset' => ['abc', 'UUENCODE', 'abc'];
        yield 'entities are no charset' => ['&eacute;', 'HTML-ENTITIES', '&eacute;'];
        yield 'html is no charset' => ['&eacute;', 'html', '&eacute;'];
        yield 'quoted-printable is no charset' => ['=C3=A9', 'quoted-printable', '=C3=A9'];
        yield 'qprint is no charset' => ['=C3=A9', 'qprint', '=C3=A9'];
        yield '7bit' => ["caf\xC3\xA9", '7bit', 'café'];
        yield '8bit' => ["caf\xC3\xA9", '8bit', 'café'];
        yield 'pass' => ["caf\xC3\xA9", 'pass', 'café'];
        yield 'auto' => ["caf\xC3\xA9", 'auto', 'café'];
        yield 'wchar' => ["caf\xC3\xA9", 'wchar', 'café'];
    }

    #[DataProvider('charsets')]
    public function testTextIsConvertedToUtf8(string $bytes, ?string $charset, string $expected): void
    {
        self::assertSame($expected, Charset::toUtf8($bytes, $charset));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function encodedWords(): iterable
    {
        yield 'no word' => ['plain text', 'plain text'];
        yield 'raw 8-bit text' => ["caf\xE9", 'caf?'];
        yield 'base64' => ['=?UTF-8?B?w6l0w6k=?=', 'été'];
        yield 'base64 in lowercase' => ['=?utf-8?b?w6l0w6k=?=', 'été'];
        yield 'quoted' => ['=?ISO-8859-1?Q?caf=E9_cr=E8me?=', 'café crème'];
        yield 'quoted in lowercase' => ['=?iso-8859-1?q?caf=E9?=', 'café'];
        yield 'text around' => ['Re: =?UTF-8?Q?caf=C3=A9?= now', 'Re: café now'];
        yield 'space between words dropped' => ["=?UTF-8?Q?a?= \r\n\t =?UTF-8?Q?b?=", 'ab'];
        yield 'text between words kept' => ['=?UTF-8?Q?a?= - =?UTF-8?Q?b?=', 'a - b'];
        yield 'a character split between words' => ['=?UTF-8?B?w6k=?= =?UTF-8?B?w6k=?= =?UTF-8?Q?=C3?= =?UTF-8?Q?=A9?=', 'ééé'];
        yield 'two sets' => ['=?ISO-8859-1?Q?=E9?= =?UTF-8?Q?=C3=A9?=', 'éé'];
        yield 'a language' => ['=?UTF-8*fr?Q?caf=C3=A9?=', 'café'];
        yield 'an unknown set' => ['=?x-bogus?Q?caf=C3=A9?=', 'café'];
        yield 'an empty word' => ['a =?UTF-8?Q??= b', 'a  b'];
        yield 'a malformed word kept' => ['=?UTF-8?X?abc?=', '=?UTF-8?X?abc?='];
        yield 'a word with a space kept' => ['=?UTF-8?Q?a b?=', '=?UTF-8?Q?a b?='];
        yield 'a word after text and space' => ['x =?UTF-8?Q?y?=', 'x y'];
        yield 'base64 without its alphabet' => ['=?UTF-8?B?***?=', ''];
        yield 'base64 without padding' => ['=?UTF-8?B?w6k?=', 'é'];
    }

    #[DataProvider('encodedWords')]
    public function testEncodedWordsAreDecoded(string $value, string $expected): void
    {
        self::assertSame($expected, EncodedWords::decode($value));
    }

    public function testATooLongCharacterSetIsNoWord(): void
    {
        $word = '=?'.str_repeat('a', 65).'?Q?x?=';

        self::assertSame($word, EncodedWords::decode($word));
        self::assertSame('x', EncodedWords::decode('=?'.str_repeat('a', 64).'?Q?x?='));
    }

    public function testBase64IsDecodedAcrossChunks(): void
    {
        $data = random_bytes(200_000);
        $encoded = chunk_split(base64_encode($data), 76, "\r\n");

        self::assertSame($data, $this->decoded($encoded, 'base64'));
        self::assertSame('ab', $this->decoded("YW\nJ=\n=", 'base64'));
        self::assertSame('a', $this->decoded('YQ', 'base64'));
        self::assertSame('', $this->decoded('Y', 'base64'));
        self::assertSame('abc', $this->decoded("YWJj\x00Z", 'base64'));
        self::assertSame('abc', $this->decoded('Y W J j !', 'base64'));
    }

    public function testBase64CarriesAnIncompleteGroupToTheNextChunk(): void
    {
        // 65,535 characters of the alphabet: the last three are carried and completed by the next chunk.
        $data = str_repeat('xyz', 21_845).'abcd';
        $encoded = base64_encode($data);

        self::assertSame($data, $this->decoded(' '.$encoded, 'base64'));
    }

    public function testQuotedPrintableIsDecodedAcrossChunks(): void
    {
        $text = str_repeat("Caf\xC3\xA9 cr\xC3\xA8me, une ligne assez longue pour \xC3\xAAtre coup\xC3\xA9e. ", 3_000);
        $encoded = quoted_printable_encode($text);

        self::assertSame($text, $this->decoded($encoded, 'quoted-printable'));
        self::assertSame($text, $this->decoded(str_replace("\r\n", "\n", $encoded), 'quoted-printable'));
    }

    public function testQuotedPrintableNeverSplitsAnEscape(): void
    {
        foreach ([65_534, 65_535, 65_536, 65_533] as $length) {
            $encoded = str_repeat('a', $length).'=C3=A9'.str_repeat('b', 10);

            self::assertSame(str_repeat('a', $length).'é'.str_repeat('b', 10), $this->decoded($encoded, 'quoted-printable'), (string) $length);
        }

        // A last `=` is a soft line break; a malformed escape is kept as it is written.
        self::assertSame('a', $this->decoded('a=', 'quoted-printable'));
        self::assertSame('=ZZ a=4', $this->decoded('=ZZ a=4', 'quoted-printable'));
    }

    public function testOtherEncodingsGiveTheOctetsAsTheyAre(): void
    {
        $data = str_repeat("line =C3\n", 20_000);

        self::assertSame($data, $this->decoded($data, '8bit'));
        self::assertSame($data, $this->decoded($data, ''));
        self::assertSame('=41', $this->decoded('=41', 'x-uuencode'));
    }

    public function testEachChunkGivenIsNotEmptyAndAtMost64KiB(): void
    {
        $sizes = [];
        TransferDecoder::decode(str_repeat('a', 140_000), 0, 140_000, 'binary', static function (string $chunk) use (&$sizes): void {
            $sizes[] = \strlen($chunk);
        });

        self::assertSame([65_536, 65_536, 8_928], $sizes);

        $chunks = 0;
        TransferDecoder::decode('====', 0, 4, 'base64', static function () use (&$chunks): void {
            ++$chunks;
        });
        TransferDecoder::decode('', 0, 0, 'quoted-printable', static function () use (&$chunks): void {
            ++$chunks;
        });
        self::assertSame(0, $chunks);
    }

    public function testOnlyTheRangeIsDecoded(): void
    {
        self::assertSame('bc', $this->decoded('abcd', 'binary', 1, 2));
        self::assertSame('a', $this->decoded('xxYQ==yy', 'base64', 2, 4));
        self::assertSame('é', $this->decoded('xx=C3=A9yy', 'quoted-printable', 2, 6));
    }

    private function decoded(string $source, string $encoding, int $offset = 0, ?int $length = null): string
    {
        $decoded = '';
        TransferDecoder::decode($source, $offset, $length ?? \strlen($source), $encoding, static function (string $chunk) use (&$decoded): void {
            $decoded .= $chunk;
        });

        return $decoded;
    }
}
