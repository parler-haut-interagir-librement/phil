<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application\Xmp;

use Phil\MediaBundle\Application\Xmp\Bytes;
use Phil\MediaBundle\Application\Xmp\IsoBoxes;
use Phil\MediaBundle\Application\Xmp\ItemLocations;
use Phil\MediaBundle\Application\Xmp\JpegXmp;
use Phil\MediaBundle\Application\Xmp\PngXmp;
use Phil\MediaBundle\Application\Xmp\WebpXmp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the writers: the largest packet of a JPEG segment, the markers that end the headers, the chunks of
 * PNG and WebP at the very end of their files, the sizes of the fields of an ISO box, and the offsets that move.
 *
 * @phpstan-import-type Locations from ItemLocations
 * @phpstan-import-type Item from ItemLocations
 */
#[CoversClass(JpegXmp::class)]
#[CoversClass(PngXmp::class)]
#[CoversClass(WebpXmp::class)]
#[CoversClass(Bytes::class)]
#[CoversClass(IsoBoxes::class)]
#[CoversClass(ItemLocations::class)]
final class XmpBoundariesTest extends TestCase
{
    private const string PACKET = '<x:xmpmeta>p</x:xmpmeta>';

    private const string HEADER = "http://ns.adobe.com/xap/1.0/\0";

    private static function app1(string $packet = self::PACKET): string
    {
        return "\xFF\xE1".pack('n', 2 + \strlen(self::HEADER.$packet)).self::HEADER.$packet;
    }

    private static function segment(int $marker, string $data): string
    {
        return "\xFF".\chr($marker & 0xFF).pack('n', 2 + \strlen($data)).$data;
    }

    public function testAJpegPacketThatFillsItsSegmentIsWrittenAndOneByteMoreIsNot(): void
    {
        $fits = str_repeat('p', 0xFFFD - \strlen(self::HEADER));

        self::assertSame("\xFF\xD8\xFF\xE1\xFF\xFF".self::HEADER.$fits."\xFF\xDA", JpegXmp::write("\xFF\xD8\xFF\xDA", $fits));
        self::assertNull(JpegXmp::write("\xFF\xD8\xFF\xDA", $fits.'p'));
    }

    public function testAFileThatDoesNotOpenAsAJpegIsRefusedWhateverItHolds(): void
    {
        self::assertNull(JpegXmp::write("XX\xFF\xDA", self::PACKET));
        self::assertNull(JpegXmp::write("\xFF\xD8\xFF", self::PACKET));
        self::assertNull(JpegXmp::write('', self::PACKET));
    }

    public function testTheEndOfImageMarkerEndsTheHeaders(): void
    {
        self::assertSame("\xFF\xD8".self::app1()."\xFF\xD9", JpegXmp::write("\xFF\xD8\xFF\xD9", self::PACKET));
    }

    public function testOnlyTheScanAndTheEndOfImageEndTheHeaders(): void
    {
        $quantisation = self::segment(0xDB, 'table');
        $startOfImage = self::segment(0xD8, '');
        $exif = self::segment(0xE1, "Exif\0\0");

        self::assertSame(
            "\xFF\xD8".self::app1().$quantisation."\xFF\xDA",
            JpegXmp::write("\xFF\xD8".$quantisation.$exif."\xFF\xDA", self::PACKET),
        );
        self::assertSame(
            "\xFF\xD8".self::app1().$startOfImage."\xFF\xDA",
            JpegXmp::write("\xFF\xD8".$startOfImage.$exif."\xFF\xDA", self::PACKET),
        );
    }

    public function testASegmentWithoutDataIsKept(): void
    {
        $empty = self::segment(0xE2, '');

        self::assertSame("\xFF\xD8".self::app1().$empty."\xFF\xDA", JpegXmp::write("\xFF\xD8".$empty."\xFF\xDA", self::PACKET));
    }

    public function testTheSegmentsOfTheJfifHeaderAreAllKeptFirst(): void
    {
        $first = self::segment(0xE0, 'JFIF');
        $second = self::segment(0xE0, 'JFXX');
        $profile = self::segment(0xE2, 'profile');

        self::assertSame(
            "\xFF\xD8".$first.$second.self::app1().$profile."\xFF\xDA",
            JpegXmp::write("\xFF\xD8".$first.$second.$profile."\xFF\xDA", self::PACKET),
        );
    }

    /**
     * @return array{string, string}
     */
    private static function pngChunks(): array
    {
        $signature = "\x89PNG\r\n\x1A\n";
        $header = self::pngChunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0));

        return [$signature, $header];
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    public function testAPngChunkKeepsItsDataAndTheNewChunkIsChecked(): void
    {
        [$signature, $header] = self::pngChunks();
        $data = self::pngChunk('IDAT', 'abcdef');
        $end = self::pngChunk('IEND', '');
        $written = PngXmp::write($signature.$header.$data.$end, self::PACKET);
        $text = self::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0".self::PACKET);

        self::assertSame($signature.$header.$text.$data.$end, $written);
    }

    public function testAFileWithoutPngSignatureIsRefusedWhateverItHolds(): void
    {
        [, $header] = self::pngChunks();

        self::assertNull(PngXmp::write('XXXXXXXX'.$header.self::pngChunk('IEND', ''), self::PACKET));
    }

    public function testAPngThatEndsInTheMiddleOfAChunkHeaderIsRefused(): void
    {
        [$signature, $header] = self::pngChunks();

        self::assertNull(PngXmp::write($signature.$header.'abc', self::PACKET));
        self::assertNull(PngXmp::write($signature.$header.str_repeat("\0", 11), self::PACKET));
        self::assertNull(PngXmp::write($signature.$header, self::PACKET));
        self::assertNull(PngXmp::write($signature.$header.pack('N', 0).'IEND', self::PACKET));
        self::assertNull(PngXmp::write($signature.$header.pack('N', 0).'IEND'."\0\0\0", self::PACKET));
    }

    private static function webpChunk(string $type, string $data): string
    {
        return $type.pack('V', \strlen($data)).$data.(1 === \strlen($data) % 2 ? "\0" : '');
    }

    private static function webp(string $body, ?int $size = null): string
    {
        return 'RIFF'.pack('V', $size ?? 4 + \strlen($body)).'WEBP'.$body;
    }

    private static function extended(int $flags, int $width, int $height): string
    {
        return \chr($flags & 0xFF)."\0\0\0".substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);
    }

    public function testALossyWebpOfTenBytesGivesItsCanvas(): void
    {
        $data = "\0\0\0\x9D\x01\x2A".pack('v', 301 | 0x4000).pack('v', 401 | 0x8000);
        $lossy = self::webpChunk('VP8 ', $data);

        self::assertSame(10, \strlen($data));
        self::assertSame(
            self::webp(self::webpChunk('VP8X', self::extended(0x04, 301, 401)).$lossy.self::webpChunk('XMP ', self::PACKET)),
            WebpXmp::write(self::webp($lossy), self::PACKET),
        );
    }

    public function testAWebpOfOnePixelIsWritten(): void
    {
        $lossy = self::webpChunk('VP8 ', "\0\0\0\x9D\x01\x2A\x01\0\x01\0");
        $lossless = self::webpChunk('VP8L', "\x2F\0\0\0\0");

        self::assertSame(
            self::webp(self::webpChunk('VP8X', self::extended(0x04, 1, 1)).$lossy.self::webpChunk('XMP ', self::PACKET)),
            WebpXmp::write(self::webp($lossy), self::PACKET),
        );
        self::assertSame(
            self::webp(self::webpChunk('VP8X', self::extended(0x04, 1, 1)).$lossless.self::webpChunk('XMP ', self::PACKET)),
            WebpXmp::write(self::webp($lossless), self::PACKET),
        );
    }

    public function testALosslessWebpOfFiveBytesGivesItsCanvasAndItsAlpha(): void
    {
        $data = "\x2F".pack('V', 299 | 399 << 14 | 1 << 28);
        $lossless = self::webpChunk('VP8L', $data);

        self::assertSame(5, \strlen($data));
        self::assertSame(
            self::webp(self::webpChunk('VP8X', self::extended(0x14, 300, 400)).$lossless.self::webpChunk('XMP ', self::PACKET)),
            WebpXmp::write(self::webp($lossless), self::PACKET),
        );
    }

    public function testAWebpWithAnotherTagIsRefused(): void
    {
        $body = self::webpChunk('VP8L', "\x2F\0\0\0\0");

        self::assertNotNull(WebpXmp::write(self::webp($body), self::PACKET));
        self::assertNull(WebpXmp::write('RIFX'.substr(self::webp($body), 4), self::PACKET));
        self::assertNull(WebpXmp::write('RIFF'.pack('V', 4 + \strlen($body)).'WEBX'.$body, self::PACKET));
        self::assertNull(WebpXmp::write('', self::PACKET));
        self::assertNull(WebpXmp::write('RIFF', self::PACKET));
    }

    public function testAWebpWhoseSizeRunsPastTheFileIsRefused(): void
    {
        $body = self::webpChunk('VP8L', "\x2F\0\0\0\0");

        self::assertNull(WebpXmp::write(self::webp($body, 4 + \strlen($body) + 1), self::PACKET));
        self::assertNull(WebpXmp::write(self::webp($body, 4 + \strlen($body) + 0x01000000), self::PACKET));
    }

    public function testTheBytesPastTheSizeOfAWebpAreNotRead(): void
    {
        $body = self::webpChunk('VP8L', "\x2F\0\0\0\0");
        $overrun = 'VP8L'.pack('V', 6)."\x2F\0\0\0\0";

        self::assertNull(WebpXmp::write(self::webp($overrun, 4 + \strlen($overrun)).'x', self::PACKET));
        self::assertNull(WebpXmp::write(self::webp($overrun, 4 + \strlen($overrun)).str_repeat("\0", 8), self::PACKET));
        self::assertNotNull(WebpXmp::write(self::webp($body).'trailing bytes', self::PACKET));
    }

    public function testAChunkOfWebpIsReadAtTheEndOfTheFileAndNotBeyondIt(): void
    {
        $lossless = self::webpChunk('VP8L', "\x2F\0\0\0\0");
        $empty = 'ABCD'.pack('V', 0);
        $written = WebpXmp::write(self::webp($lossless.$empty), self::PACKET);

        self::assertSame(self::webp(self::webpChunk('VP8X', self::extended(0x04, 1, 1)).$lossless.$empty.self::webpChunk('XMP ', self::PACKET)), $written);
        self::assertSame(
            self::webp(self::webpChunk('VP8X', self::extended(0x04, 1, 1)).$lossless.self::webpChunk('XMP ', self::PACKET)),
            WebpXmp::write(self::webp($lossless.str_repeat("\0", 7)), self::PACKET),
        );
    }

    public function testAChunkOfWebpWhoseSizeHasAHighByteIsRefused(): void
    {
        $chunk = 'VP8L'.pack('V', 0x01000005)."\x2F\0\0\0\0";

        self::assertNull(WebpXmp::write(self::webp($chunk), self::PACKET));
    }

    public function testAChunkThatRunsOneByteBeyondItsFileIsRefused(): void
    {
        $chunk = 'VP8L'.pack('V', 6)."\x2F\0\0\0\0";

        self::assertNull(WebpXmp::write(self::webp($chunk), self::PACKET));
    }

    public function testBytesBeforeTheStartReadAsZeros(): void
    {
        self::assertSame(0x07, Bytes::bigEndian("\x07\x08", -1, 2));
        self::assertSame(0x0100, Bytes::littleEndian("\x01\x02", -1, 2));
        self::assertSame(0x0102, Bytes::bigEndian("\x01\x02", 0, 2));
        self::assertSame(0x0201, Bytes::littleEndian("\x01\x02", 0, 2));
    }

    public function testAnIsoBoxOfEightBytesFillsItsRange(): void
    {
        self::assertSame([['free', 0, 8, 8]], IsoBoxes::read(pack('N', 8).'free', 0, 8));
        self::assertSame([['free', 3, 11, 11], ['moov', 11, 19, 19]], IsoBoxes::read('abc'.pack('N', 8).'free'.pack('N', 8).'moov', 3, 19));
        self::assertSame([], IsoBoxes::read('', 0, 0));
    }

    public function testAnIsoBoxWithALargeSizeKeepsItsLargeHeader(): void
    {
        $box = pack('N', 1).'free'.pack('J', 16);

        self::assertSame([['free', 0, 16, 16]], IsoBoxes::read($box, 0, 16));
        self::assertSame([['free', 3, 19, 19]], IsoBoxes::read('abc'.$box, 3, 19));
        self::assertSame([['free', 0, 16, 18]], IsoBoxes::read(pack('N', 1).'free'.pack('J', 18).'ab', 0, 18));
        self::assertNull(IsoBoxes::read(pack('N', 1).'free'.pack('J', 15), 0, 16));
        self::assertNull(IsoBoxes::read(pack('N', 1).'freeabc', 0, 11));
        self::assertNull(IsoBoxes::read(pack('N', 1).'free'."\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", 0, 16));
    }

    public function testAnIsoBoxThatDoesNotFitItsRangeIsRefused(): void
    {
        self::assertNull(IsoBoxes::read(pack('N', 7).'free', 0, 8));
        self::assertNull(IsoBoxes::read(pack('N', 9).'free', 0, 8));
        self::assertNull(IsoBoxes::read(pack('N', 0).'free', 0, 8));
        self::assertNull(IsoBoxes::read('abcd'.pack('N', 12).'free', 4, 12));
        self::assertNull(IsoBoxes::read('abcd'.pack('N', 8).'freeabc', 4, 15));
        self::assertNull(IsoBoxes::read('abc', 0, 3));
        self::assertNull(IsoBoxes::read('abcdefg', 0, 7));
    }

    public function testTheUnsignedIntegersOfAnIsoBox(): void
    {
        self::assertSame(0, IsoBoxes::uint('abc', 0, 0, 3));
        self::assertSame(0x0102, IsoBoxes::uint("\x01\x02", 0, 2, 2));
        self::assertSame(0x01020304, IsoBoxes::uint("\x01\x02\x03\x04", 0, 4, 4));
        self::assertSame(0x0102030405060708, IsoBoxes::uint("\x01\x02\x03\x04\x05\x06\x07\x08", 0, 8, 8));
        self::assertSame(0x0203, IsoBoxes::uint("\x01\x02\x03", 1, 2, 3));
        self::assertNull(IsoBoxes::uint("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", 0, 8, 8));
        self::assertNull(IsoBoxes::uint('abcd', 2, 4, 4));
        self::assertNull(IsoBoxes::uint('abcd', 3, 2, 4));
        self::assertSame(0x6263, IsoBoxes::uint('abcd', 1, 2, 4));
    }

    public function testAnIntegerOfThreeBytesIsNotAFieldOfAnIsoBox(): void
    {
        self::assertNull(IsoBoxes::uint('abc', 0, 3, 3));
        self::assertNull(IsoBoxes::uint('abcdefghi', 0, 5, 9));
    }

    public function testTheUnsignedIntegersWrittenInEachWidth(): void
    {
        self::assertSame('', IsoBoxes::bytes(5, 0));
        self::assertSame("\0\0\0\5", IsoBoxes::bytes(5, 4));
        self::assertSame("\0\0\0\0\0\0\0\5", IsoBoxes::bytes(5, 8));
    }

    /**
     * @param list<Item> $items
     *
     * @return Locations
     */
    private static function locations(array $items, int $offset = 4, int $length = 4, int $base = 0, int $version = 0): array
    {
        return [
            'version' => $version,
            'head' => \chr($version & 0xFF)."\0\0\0".\chr(($offset << 4 | $length) & 0xFF).\chr(($base << 4) & 0xFF),
            'sizes' => ['offset' => $offset, 'length' => $length, 'base' => $base, 'index' => 0],
            'items' => $items,
        ];
    }

    /**
     * @param list<array{int, int, int}> $extents
     *
     * @return Item
     */
    private static function item(int $id, array $extents, int $base = 0, int $method = 0, int $reference = 0): array
    {
        return ['id' => $id, 'method' => $method, 'reference' => $reference, 'base' => $base, 'extents' => $extents];
    }

    /**
     * @param Locations $locations
     *
     * @return list<Item>|null the items of the box written, `null` when nothing is written
     */
    private static function moved(array $locations, int $metaEnd = 100, int $delta = 7): ?array
    {
        $box = ItemLocations::write($locations, $metaEnd, $delta, 5000, 99, 11);

        if (null === $box) {
            return null;
        }

        $read = ItemLocations::read($box, ['iloc', 0, 8, \strlen($box)]);
        self::assertNotNull($read);

        return $read['items'];
    }

    public function testAnExtentAtTheEndOfTheMetaBoxMovesAndOneBeforeItStays(): void
    {
        $items = self::moved(self::locations([self::item(1, [[0, 99, 5], [0, 100, 5], [0, 0, 5]])]));
        $new = self::item(99, [[0, 5000, 11]]);

        self::assertSame([self::item(1, [[0, 99, 5], [0, 107, 5], [0, 0, 5]]), $new], $items);
    }

    public function testTheBaseMovesWhenEveryExtentLiesAfterTheMetaBox(): void
    {
        $locations = self::locations([self::item(1, [[0, 10, 5], [0, 20, 5]], 95)], 4, 4, 4);

        self::assertSame([self::item(1, [[0, 10, 5], [0, 20, 5]], 102), self::item(99, [[0, 5000, 11]])], self::moved($locations));
    }

    public function testTheExtentsMoveWhenTheBaseIsNotStoredOrNotEveryExtentIsAfter(): void
    {
        $withBase = self::locations([self::item(1, [[0, 4, 5], [0, 10, 5]], 95)], 4, 4, 4);
        $withoutBase = self::locations([self::item(1, [[0, 200, 5]])]);

        self::assertSame([self::item(1, [[0, 4, 5], [0, 17, 5]], 95), self::item(99, [[0, 5000, 11]], 0)], self::moved($withBase));
        self::assertSame([self::item(1, [[0, 207, 5]]), self::item(99, [[0, 5000, 11]])], self::moved($withoutBase));
    }

    public function testAnItemWithoutExtentsKeepsItsBase(): void
    {
        $locations = self::locations([self::item(1, [], 50)], 4, 4, 4);

        self::assertSame([self::item(1, [], 50), self::item(99, [[0, 5000, 11]])], self::moved($locations));
    }

    public function testAnItemOutsideTheFileIsLeftAlone(): void
    {
        $byMethod = self::locations([self::item(1, [[0, 200, 5]], 0, 1)], 4, 4, 0, 1);
        $byReference = self::locations([self::item(1, [[0, 200, 5]], 0, 0, 3)], 4, 4, 0, 1);

        self::assertSame([self::item(1, [[0, 200, 5]], 0, 1), self::item(99, [[0, 5000, 11]])], self::moved($byMethod));
        self::assertSame([self::item(1, [[0, 200, 5]], 0, 0, 3), self::item(99, [[0, 5000, 11]])], self::moved($byReference));
    }

    public function testAnExtentOfNoLengthCannotBeMoved(): void
    {
        self::assertNull(self::moved(self::locations([self::item(1, [[0, 200, 0]])])));
        self::assertNull(self::moved(self::locations([self::item(1, [[0, 0, 0]])])));
        self::assertSame([self::item(1, [[0, 0, 5]]), self::item(99, [[0, 5000, 11]])], self::moved(self::locations([self::item(1, [[0, 0, 5]])])));
    }

    public function testAnOffsetThatOutgrowsItsFieldIsRefused(): void
    {
        self::assertSame(0xFFFFFFFF, self::moved(self::locations([self::item(1, [[0, 0xFFFFFFF8, 5]])]))[0]['extents'][0][1] ?? null);
        self::assertNull(self::moved(self::locations([self::item(1, [[0, 0xFFFFFFF9, 5]])])));
    }

    public function testABaseThatOutgrowsItsFieldIsRefused(): void
    {
        self::assertSame(0xFFFFFFFF, self::moved(self::locations([self::item(1, [[0, 200, 5]], 0xFFFFFFF8)], 4, 4, 4))[0]['base'] ?? null);
        self::assertNull(self::moved(self::locations([self::item(1, [[0, 200, 5]], 0xFFFFFFF9)], 4, 4, 4)));
    }

    public function testTheNewItemNeedsAnOffsetAndALength(): void
    {
        self::assertNull(self::moved(self::locations([], 0, 4)));
        self::assertNull(self::moved(self::locations([], 4, 0)));
        self::assertNull(self::moved(self::locations([], 0, 0)));
        self::assertNotNull(self::moved(self::locations([], 8, 8)));
    }
}
