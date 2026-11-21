<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application\Xmp;

use GdImage;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\Xmp\AvifXmp;
use Phil\MediaBundle\Application\Xmp\Bytes;
use Phil\MediaBundle\Application\Xmp\IsoBoxes;
use Phil\MediaBundle\Application\Xmp\ItemLocations;
use Phil\MediaBundle\Application\Xmp\JpegXmp;
use Phil\MediaBundle\Application\Xmp\PngXmp;
use Phil\MediaBundle\Application\Xmp\SourceTypeXmp;
use Phil\MediaBundle\Application\Xmp\WebpXmp;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Image\BinaryEncoder;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[CoversClass(SourceTypeXmp::class)]
#[CoversClass(JpegXmp::class)]
#[CoversClass(PngXmp::class)]
#[CoversClass(WebpXmp::class)]
#[CoversClass(AvifXmp::class)]
#[CoversClass(IsoBoxes::class)]
#[CoversClass(ItemLocations::class)]
#[CoversClass(Bytes::class)]
final class SourceTypeXmpTest extends TestCase
{
    private const string GENERATED = Metadata::URI.'trainedAlgorithmicMedia';

    public function testThePacketCarriesTheSourceTypeAndNothingElse(): void
    {
        $packet = SourceTypeXmp::packet(self::GENERATED);

        self::assertStringStartsWith('<?xpacket begin="'."\u{FEFF}".'" id="W5M0MpCehiHzreSzNTczkc9d"?>', $packet);
        self::assertStringEndsWith('<?xpacket end="r"?>', $packet);
        self::assertStringContainsString('xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"', $packet);
        self::assertSame([SourceTypeXmp::XMP_PROPERTY => self::GENERATED], Metadata::properties(Metadata::packets($packet)[0]));
        self::assertSame([SourceTypeXmp::XMP_PROPERTY => 'https://cv.iptc.org/newscodes/digitalsourcetype/'.str_repeat('a', 64)], Metadata::properties(SourceTypeXmp::packet('https://cv.iptc.org/newscodes/digitalsourcetype/'.str_repeat('a', 64))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignAddresses(): iterable
    {
        yield 'another vocabulary' => ['http://cv.iptc.org/newscodes/genre/trainedAlgorithmicMedia'];
        yield 'a quote' => [Metadata::URI.'a"b'];
        yield 'markup' => [Metadata::URI.'a<b'];
        yield 'a trailing line' => [self::GENERATED."\n"];
        yield 'a leading space' => [' '.self::GENERATED];
        yield 'a code too long' => [Metadata::URI.str_repeat('a', 65)];
        yield 'no code' => [Metadata::URI];
        yield 'a digit' => [Metadata::URI.'a1'];
        yield 'another scheme' => ['ftp://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia'];
        yield 'another host' => ['http://cvxiptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia'];
    }

    #[DataProvider('foreignAddresses')]
    public function testAnAddressOutsideTheVocabularyIsRefused(string $uri): void
    {
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not the address of an IPTC digital source type.', $uri));

        SourceTypeXmp::write(Images::png(4, 4), 'png', $uri);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function renderings(): iterable
    {
        foreach (['gd', 'cwebp and avifenc'] as $processor) {
            foreach (['jpeg', 'png', 'webp', 'avif'] as $type) {
                yield $processor.' '.$type => [$processor, $type];
            }
        }
    }

    #[DataProvider('renderings')]
    public function testEachRenderingCarriesExactlyTheSourceType(string $processor, string $type): void
    {
        $rendered = $this->processor($processor, $type)->render(Images::png(), ImageFormat::of('t', 60, 40), [0.5, 0.5], $type);
        $marked = SourceTypeXmp::write($rendered, $type, self::GENERATED);

        self::assertSame([], Metadata::packets($rendered));
        self::assertCount(1, Metadata::packets($marked));
        self::assertSame([SourceTypeXmp::XMP_PROPERTY => self::GENERATED], Metadata::properties(Metadata::packets($marked)[0]));
        self::assertStringNotContainsString('Exif', $marked);
        self::assertStringNotContainsString('CREATOR', $marked);
        self::assertSame([60, 40], \array_slice((array) getimagesizefromstring($marked), 0, 2));
        $image = imagecreatefromstring($marked);
        self::assertInstanceOf(GdImage::class, $image);
        self::assertSame([60, 40], [imagesx($image), imagesy($image)]);
        $this->assertColour([255, 0, 0], $image, 5, 20);
        $this->assertColour([0, 0, 255], $image, 55, 20);
    }

    public function testAJpegKeepsItsHeaderAndProfileAndDropsTheRest(): void
    {
        $jpeg = Images::jpeg(20, 10);
        $tagged = "\xFF\xD8".substr($jpeg, 2, 18)
            .Metadata::exifWithGps()
            .Metadata::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>old</x:xmpmeta>")
            .Metadata::jpegSegment(0xE2, "ICC_PROFILE\0\x01\x01profile")
            .Metadata::jpegSegment(0xED, 'Photoshop 3.0')
            .Metadata::jpegSegment(0xFE, 'a comment')
            .substr($jpeg, 20);
        self::assertSame(0xE0, Metadata::jpegSegments($jpeg)[0]);

        $marked = SourceTypeXmp::write($tagged, 'jpeg', self::GENERATED);

        self::assertSame([0xE0, 0xE1, 0xE2], \array_slice(Metadata::jpegSegments($marked), 0, 3));
        self::assertNotContains(0xED, Metadata::jpegSegments($marked));
        self::assertNotContains(0xFE, Metadata::jpegSegments($marked));
        self::assertCount(1, Metadata::packets($marked));
        self::assertStringNotContainsString('Exif', $marked);
        self::assertStringContainsString('ICC_PROFILE', $marked);
        self::assertSame(substr($jpeg, -100), substr($marked, -100));
        self::assertInstanceOf(GdImage::class, imagecreatefromstring($marked));
    }

    public function testAJpegWithoutJfifHeaderGetsThePacketFirst(): void
    {
        $jpeg = Images::jpeg(20, 10);
        $bare = "\xFF\xD8".substr($jpeg, 20);

        $marked = SourceTypeXmp::write($bare, 'jpeg', self::GENERATED);

        self::assertSame(0xE1, Metadata::jpegSegments($marked)[0]);
        self::assertContains(0xFE, Metadata::jpegSegments($bare));
        self::assertSame(array_values(array_diff(Metadata::jpegSegments($bare), [0xFE])), \array_slice(Metadata::jpegSegments($marked), 1));
        self::assertSame("\xFF\xD8\xFF\xE1".pack('n', 2 + 29 + \strlen(SourceTypeXmp::packet(self::GENERATED)))."http://ns.adobe.com/xap/1.0/\0", substr($marked, 0, 35));
    }

    public function testAPngGetsThePacketAfterItsHeaderAndDropsItsTextAndExif(): void
    {
        $png = Images::png(8, 4);
        $tagged = substr($png, 0, 33)
            .Metadata::pngChunk('tEXt', "Comment\0hello")
            .Metadata::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0<x:xmpmeta>old</x:xmpmeta>")
            .Metadata::pngChunk('zTXt', "Author\0\0x")
            .Metadata::pngChunk('tIME', "\x07\xEA\x0B\x15\0\0\0")
            .Metadata::pngChunk('eXIf', 'MM')
            .Metadata::pngChunk('gAMA', "\0\0\xB1\x8F")
            .substr($png, 33);

        $marked = SourceTypeXmp::write($tagged, 'png', self::GENERATED);

        self::assertSame(['IHDR', 'iTXt', 'gAMA', ...\array_slice(Metadata::pngChunks($png), 1)], Metadata::pngChunks($marked));
        self::assertSame(substr($marked, 33, 8 + 17 + 5), pack('N', 22 + \strlen(SourceTypeXmp::packet(self::GENERATED))).'iTXt'."XML:com.adobe.xmp\0\0\0\0\0");
        self::assertCount(1, Metadata::packets($marked));
        self::assertSame(self::GENERATED, Metadata::properties(Metadata::packets($marked)[0])[SourceTypeXmp::XMP_PROPERTY]);
        self::assertInstanceOf(GdImage::class, imagecreatefromstring($marked));
    }

    public function testAPngEndsAtItsEnd(): void
    {
        $marked = SourceTypeXmp::write(Images::png(8, 4).'trailing', 'png', self::GENERATED);

        self::assertStringEndsWith('IEND'.pack('N', crc32('IEND')), $marked);
    }

    public function testALossyWebpBecomesExtended(): void
    {
        $webp = $this->webp(30, 10, 80, false);
        self::assertSame(['VP8 '], Metadata::webpChunks($webp));

        $marked = SourceTypeXmp::write($webp, 'webp', self::GENERATED);

        self::assertSame(['VP8X', 'VP8 ', 'XMP '], Metadata::webpChunks($marked));
        self::assertSame("\x04\0\0\0".substr(pack('V', 29), 0, 3).substr(pack('V', 9), 0, 3), substr($marked, 20, 10));
        self::assertSame(\strlen($marked) - 8, Bytes::littleEndian($marked, 4, 4));
        self::assertInstanceOf(GdImage::class, imagecreatefromstring($marked));
    }

    public function testALosslessWebpKeepsItsAlpha(): void
    {
        $webp = $this->webp(30, 10, 101, true);
        self::assertSame(['VP8L'], Metadata::webpChunks($webp));

        $marked = SourceTypeXmp::write($webp, 'webp', self::GENERATED);

        self::assertSame(['VP8X', 'VP8L', 'XMP '], Metadata::webpChunks($marked));
        self::assertSame("\x14\0\0\0".substr(pack('V', 29), 0, 3).substr(pack('V', 9), 0, 3), substr($marked, 20, 10));
        $image = imagecreatefromstring($marked);
        self::assertInstanceOf(GdImage::class, $image);
        self::assertGreaterThan(0, imagecolorat($image, 3, 3) >> 24 & 0x7F);
    }

    public function testALosslessWebpWithoutAlphaSaysSo(): void
    {
        $marked = SourceTypeXmp::write($this->webp(31, 11, 101, false), 'webp', self::GENERATED);

        self::assertSame("\x04\0\0\0".substr(pack('V', 30), 0, 3).substr(pack('V', 10), 0, 3), substr($marked, 20, 10));
    }

    public function testAnExtendedWebpDropsItsExifAndXmp(): void
    {
        $webp = $this->webp(30, 10, 80, true);
        $chunks = substr($webp, 12);
        self::assertSame('VP8X', substr($chunks, 0, 4));
        $flags = \chr(\ord($chunks[8]) | 0x08 | 0x04);
        $tagged = substr($chunks, 0, 8).$flags.substr($chunks, 9).Metadata::webpChunk('EXIF', 'MM*').Metadata::webpChunk('XMP ', '<x:xmpmeta>old</x:xmpmeta>');
        $tagged = 'RIFF'.pack('V', 4 + \strlen($tagged)).'WEBP'.$tagged;

        $marked = SourceTypeXmp::write($tagged, 'webp', self::GENERATED);

        self::assertSame([...Metadata::webpChunks($webp), 'XMP '], Metadata::webpChunks($marked));
        self::assertSame(\chr((\ord($chunks[8]) & ~0x08) | 0x04), $marked[20]);
        self::assertSame(substr($chunks, 9, 9), substr($marked, 21, 9));
        self::assertCount(1, Metadata::packets($marked));
        self::assertInstanceOf(GdImage::class, imagecreatefromstring($marked));
    }

    public function testAnOddChunkIsPadded(): void
    {
        $webp = $this->webp(30, 10, 80, false);
        $odd = substr($webp, 12).Metadata::webpChunk('ABCD', 'odd');
        $marked = SourceTypeXmp::write('RIFF'.pack('V', 4 + \strlen($odd)).'WEBP'.$odd, 'webp', self::GENERATED);

        self::assertSame(['VP8X', 'VP8 ', 'ABCD', 'XMP '], Metadata::webpChunks($marked));
        self::assertStringContainsString('ABCD'.pack('V', 3)."odd\0XMP ", $marked);
    }

    public function testAnAvifGetsAMetadataItemThatDescribesItsPrimaryImage(): void
    {
        $avif = $this->avif(false);
        $marked = SourceTypeXmp::write($avif, 'avif', self::GENERATED);

        self::assertStringContainsString("mime\0application/rdf+xml\0", $marked);
        self::assertStringContainsString('cdsc'.pack('nnn', 2, 1, 1), $marked);
        self::assertStringEndsWith(pack('N', 8 + \strlen(SourceTypeXmp::packet(self::GENERATED))).'mdat'.SourceTypeXmp::packet(self::GENERATED), $marked);
        self::assertCount(1, Metadata::packets($marked));
        $image = imagecreatefromstring($marked);
        self::assertInstanceOf(GdImage::class, $image);
        $this->assertColour([255, 0, 0], $image, 5, 10);
        $this->assertColour([0, 0, 255], $image, 55, 10);
    }

    public function testAnAvifWithReferencesKeepsThem(): void
    {
        $avif = $this->avif(true);
        self::assertStringContainsString('auxl', $avif);

        $marked = SourceTypeXmp::write($avif, 'avif', self::GENERATED);

        self::assertStringContainsString('auxl', $marked);
        self::assertStringContainsString('cdsc'.pack('nnn', 3, 1, 1), $marked);
        $image = imagecreatefromstring($marked);
        self::assertInstanceOf(GdImage::class, $image);
        self::assertGreaterThan(0, imagecolorat($image, 3, 3) >> 24 & 0x7F);
        self::assertSame(substr_count($avif, 'infe') + 1, substr_count($marked, 'infe'));
    }

    public function testAnAvifIsReadAsHeifReadsIt(): void
    {
        $finder = new ExecutableFinder()->find('heif-info');

        if (null === $finder) {
            self::markTestSkipped('heif-info, of libheif, is not installed: the metadata item is not checked by a HEIF reader.');
        }

        $file = sys_get_temp_dir().'/phil-media-xmp-'.bin2hex(random_bytes(4)).'.avif';
        file_put_contents($file, SourceTypeXmp::write($this->avif(true), 'avif', self::GENERATED));

        try {
            $process = new Process([$finder, $file]);
            $process->mustRun();
            self::assertMatchesRegularExpression('{XMP: \d+ bytes}', $process->getOutput());
        } finally {
            unlink($file);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        $jpeg = Images::jpeg(4, 4);
        $png = Images::png(4, 4);
        $vp8 = static fn (string $data): string => 'RIFF'.pack('V', 4 + \strlen(Metadata::webpChunk('VP8 ', $data))).'WEBP'.Metadata::webpChunk('VP8 ', $data);
        $vp8l = static fn (string $data): string => 'RIFF'.pack('V', 4 + \strlen(Metadata::webpChunk('VP8L', $data))).'WEBP'.Metadata::webpChunk('VP8L', $data);

        yield 'another type' => ['gif', 'GIF89a'];
        yield 'not a jpeg' => ['jpeg', 'nope'];
        yield 'a jpeg without scan' => ['jpeg', substr($jpeg, 0, 20)];
        yield 'a jpeg segment past the end' => ['jpeg', "\xFF\xD8\xFF\xE0\x00\x40JFIF"];
        yield 'a jpeg segment too short' => ['jpeg', "\xFF\xD8\xFF\xE0\x00\x01JFIF\xFF\xDA"];
        yield 'a jpeg cut in a marker' => ['jpeg', "\xFF\xD8\xFF\xE0\x00"];
        yield 'a jpeg with garbage' => ['jpeg', "\xFF\xD8garbage"];
        yield 'not a png' => ['png', 'nope'];
        yield 'a png without header' => ['png', substr($png, 0, 8).Metadata::pngChunk('IDAT', 'x').Metadata::pngChunk('IEND', '')];
        yield 'a png chunk past the end' => ['png', substr($png, 0, -6)];
        yield 'a png without end' => ['png', substr($png, 0, -12)];
        yield 'not a webp' => ['webp', 'RIFF'.pack('V', 4).'WAVE'];
        yield 'a webp shorter than it says' => ['webp', 'RIFF'.pack('V', 100).'WEBP'];
        yield 'a webp chunk past the end' => ['webp', 'RIFF'.pack('V', 16).'WEBPVP8 '.pack('V', 50)];
        yield 'an empty webp' => ['webp', 'RIFF'.pack('V', 4).'WEBP'];
        yield 'a webp that opens on another chunk' => ['webp', 'RIFF'.pack('V', 4 + 10).'WEBP'.Metadata::webpChunk('ALPH', 'aa')];
        yield 'a webp header of the wrong size' => ['webp', 'RIFF'.pack('V', 4 + 12).'WEBP'.Metadata::webpChunk('VP8X', 'abcd')];
        yield 'a lossy webp without start code' => ['webp', $vp8("\0\0\0\0\0\0\x0A\0\x0A\0")];
        yield 'a lossy webp too short' => ['webp', $vp8("\0\0\0\x9D\x01\x2A\x0A\0\x0A")];
        yield 'a lossy webp without width' => ['webp', $vp8("\0\0\0\x9D\x01\x2A\0\0\x0A\0")];
        yield 'a lossy webp without height' => ['webp', $vp8("\0\0\0\x9D\x01\x2A\x0A\0\0\xC0")];
        yield 'a lossless webp without signature' => ['webp', $vp8l("\x2E\0\0\0\0")];
        yield 'a lossless webp too short' => ['webp', $vp8l("\x2F\0\0\0")];
        yield 'not an avif' => ['avif', 'nope'];
        yield 'an avif without ftyp' => ['avif', pack('N', 8).'meta'];
        yield 'an avif box sized to the end' => ['avif', pack('N', 0).'ftyp'];
        yield 'an avif box too short' => ['avif', pack('N', 4).'ftyp'];
        yield 'an avif box past the end' => ['avif', pack('N', 100).'ftyp'];
        yield 'an avif cut in a box header' => ['avif', pack('N', 8).'ftyp'."\0\0"];
        yield 'an avif without meta' => ['avif', pack('N', 8).'ftyp'];
        yield 'an avif with two metas' => ['avif', pack('N', 8).'ftyp'.pack('N', 12).'meta'."\0\0\0\0".pack('N', 12).'meta'."\0\0\0\0"];
        yield 'an avif meta without its version' => ['avif', pack('N', 8).'ftyp'.pack('N', 10).'meta'."\0\0"];
        yield 'an avif meta without items' => ['avif', pack('N', 8).'ftyp'.pack('N', 12).'meta'."\0\0\0\0"];
    }

    #[DataProvider('unreadable')]
    public function testAnImageTheWriterCannotReadComesBackUnchanged(string $type, string $image): void
    {
        self::assertSame($image, SourceTypeXmp::write($image, $type, self::GENERATED));
    }

    /**
     * @return iterable<string, array{callable(string): ?string}>
     */
    public static function brokenAvifs(): iterable
    {
        yield 'a second hdlr' => [static fn (string $meta): string => $meta.self::box('hdlr', "\0\0\0\0\0\0\0\0pict")];
        yield 'no picture handler' => [static fn (string $meta): string => str_replace('pict', 'vide', $meta)];
        yield 'no pitm' => [static fn (string $meta): string => str_replace('pitm', 'pitX', $meta)];
        yield 'no iinf' => [static fn (string $meta): string => str_replace('iinf', 'iinX', $meta)];
        yield 'no iloc' => [static fn (string $meta): string => str_replace('iloc', 'ilox', $meta)];
        yield 'an iloc of version 3' => [static fn (string $meta): string => preg_replace('{iloc\x00}', "iloc\x03", $meta) ?? ''];
        yield 'an iloc field of 3 bytes' => [static fn (string $meta): string => preg_replace('{(iloc.{4})\x44}s', "\$1\x34", $meta) ?? ''];
        yield 'an iloc without offsets' => [static fn (string $meta): string => preg_replace('{(iloc.{4})\x44}s', "\$1\x04", $meta) ?? ''];
        yield 'an iloc without lengths' => [static fn (string $meta): string => preg_replace('{(iloc.{4})\x44}s', "\$1\x40", $meta) ?? ''];
        yield 'an infe of another kind' => [static fn (string $meta): string => str_replace('infe', 'infX', $meta)];
    }

    /**
     * @param callable(string): string $break
     */
    #[DataProvider('brokenAvifs')]
    public function testAnAvifTheWriterCannotExtendComesBackUnchanged(callable $break): void
    {
        $avif = $this->avif(false);
        $start = (int) strpos($avif, 'meta') - 4;
        $end = $start + Bytes::bigEndian($avif, $start, 4);
        $broken = $break(substr($avif, $start + 8, $end - $start - 8));
        $avif = substr($avif, 0, $start).pack('N', 8 + \strlen($broken)).'meta'.$broken.substr($avif, $end);

        self::assertSame($avif, SourceTypeXmp::write($avif, 'avif', self::GENERATED));
    }

    public function testExiftoolReadsOnlyTheSourceType(): void
    {
        $exiftool = new ExecutableFinder()->find('exiftool');

        if (null === $exiftool) {
            self::markTestSkipped("exiftool is not installed: the renderings are checked by the tests' own readers only.");
        }

        foreach (['jpeg', 'png', 'webp', 'avif'] as $type) {
            $file = sys_get_temp_dir().'/phil-media-xmp-'.bin2hex(random_bytes(4)).'.'.$type;
            file_put_contents($file, SourceTypeXmp::write(new GdImageProcessor()->render(Images::png(), ImageFormat::of('t', 60, 40), [0.5, 0.5], $type), $type, self::GENERATED));

            try {
                $process = new Process([$exiftool, '-j', '-G1', '-XMP:all', '-EXIF:all', '-GPS:all', '-IPTC:all', $file]);
                $process->mustRun();
                /** @var list<array<string, mixed>> $read */
                $read = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
                $tags = array_filter($read[0], static fn (string $tag): bool => 'SourceFile' !== $tag && !str_starts_with($tag, 'XMP-x:'), \ARRAY_FILTER_USE_KEY);
                self::assertSame(['XMP-iptcExt:DigitalSourceType' => self::GENERATED], $tags, $type);
            } finally {
                unlink($file);
            }
        }
    }

    private function processor(string $name, string $type): ImageProcessor
    {
        if ('gd' === $name || !\in_array($type, ['webp', 'avif'], true)) {
            return new GdImageProcessor();
        }

        $encoder = BinaryEncoder::find($type);

        if (!$encoder instanceof BinaryEncoder) {
            self::markTestSkipped(\sprintf('%s is not installed.', BinaryEncoder::BINARIES[$type]));
        }

        return new GdImageProcessor(encoders: [$type => $encoder]);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function webp(int $width, int $height, int $quality, bool $alpha): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, $alpha);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 255, 0, $alpha ? 60 : 0));
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function avif(bool $alpha): string
    {
        $image = imagecreatefromstring(Images::png(60, 20));
        self::assertInstanceOf(GdImage::class, $image);

        if ($alpha) {
            $image = imagecreatetruecolor(20, 20);
            self::assertInstanceOf(GdImage::class, $image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 255, 0, 60));
        }

        ob_start();
        imageavif($image, null, 90);

        return (string) ob_get_clean();
    }

    private static function box(string $type, string $payload): string
    {
        return pack('N', 8 + \strlen($payload)).$type.$payload;
    }

    /**
     * @param array{int, int, int} $expected
     */
    private function assertColour(array $expected, GdImage $image, int $x, int $y): void
    {
        $colour = imagecolorat($image, $x, $y);

        foreach ([$colour >> 16 & 0xFF, $colour >> 8 & 0xFF, $colour & 0xFF] as $channel => $value) {
            self::assertEqualsWithDelta($expected[$channel], $value, 40);
        }
    }
}
