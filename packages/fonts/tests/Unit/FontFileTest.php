<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Closure;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Exception\UnsupportedFont;
use Phil\Fonts\Font;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\Fonts\Name\NameId;
use Phil\Fonts\Outlines;
use Phil\Fonts\Reader\FaceReader;
use Phil\Fonts\Reader\FontReader;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontFile::class)]
#[CoversClass(FontReader::class)]
#[CoversClass(FaceReader::class)]
#[CoversClass(InvalidFont::class)]
#[CoversClass(UnsupportedFont::class)]
final class FontFileTest extends TestCase
{
    public function testReadsATrueTypeFile(): void
    {
        $bytes = FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'Example Sans', 6 => 'ExampleSans-Regular']), 'glyf' => '']);
        $file = FontFile::fromBytes($bytes);

        self::assertSame(Format::TrueType, $file->format);
        self::assertSame(hash('sha256', $bytes), $file->sha256);
        self::assertNull($file->extendedMetadata);
        self::assertCount(1, $file->faces);
        self::assertSame('Example Sans', $file->face()->family());
        self::assertSame('ExampleSans-Regular', $file->face(0)->postScriptName());
        self::assertSame(Outlines::TrueType, $file->face()->outlines);
    }

    public function testReadsTheAppleTrueTypeSignatureAndOpenType(): void
    {
        $tables = ['name' => FontBuilder::names([1 => 'A']), 'CFF ' => ''];

        self::assertSame(Format::TrueType, FontFile::fromBytes(FontBuilder::sfnt($tables, 'true'))->format);

        $openType = FontFile::fromBytes(FontBuilder::sfnt($tables, 'OTTO'));
        self::assertSame(Format::OpenType, $openType->format);
        self::assertSame('A', $openType->face()->family());
        self::assertSame(Outlines::PostScript, $openType->face()->outlines);
        self::assertSame(Outlines::PostScript, FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([]), 'CFF2' => ''], 'OTTO'))->face()->outlines);
        self::assertSame(Outlines::None, FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([])]))->face()->outlines);
    }

    public function testReadsEveryFaceOfACollection(): void
    {
        $file = FontFile::fromBytes(FontBuilder::collection([
            ['name' => FontBuilder::names([1 => 'Family', 2 => 'Regular'])],
            ['name' => FontBuilder::names([1 => 'Family', 2 => 'Bold']), 'OS/2' => FontBuilder::os2(weight: 700)],
        ]));

        self::assertSame(Format::Collection, $file->format);
        self::assertSame(['Regular', 'Bold'], array_map(static fn (Font $face): string => $face->subfamily(), $file->faces));
        self::assertSame(700, $file->face(1)->weight);
    }

    public function testRefusesAFaceMissingFromTheFile(): void
    {
        $file = FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([])]));

        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('The font file has no face 1; it has 1.');

        $file->face(1);
    }

    public function testRefusesAnEmptyCollectionAndAFaceThatIsNoSfnt(): void
    {
        try {
            FontFile::fromBytes('ttcf'.FontBuilder::u16(1).FontBuilder::u16(0).FontBuilder::u32(0));
            self::fail('An empty collection must be refused.');
        } catch (InvalidFont $invalidFont) {
            self::assertSame('The collection holds no face.', $invalidFont->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A face of the collection is not an sfnt.');

        FontFile::fromBytes('ttcf'.FontBuilder::u16(1).FontBuilder::u16(0).FontBuilder::u32(1).FontBuilder::u32(16).'wOFF'.str_repeat("\0", 12));
    }

    public function testReadsAWoffFileCompressedOrNot(): void
    {
        $tables = ['name' => FontBuilder::names([1 => 'Woff Sans']), 'OS/2' => FontBuilder::os2(weight: 300), 'glyf' => ''];
        $metadata = '<metadata version="1.0"><licensee name="Example"/><licensee name="Example"/></metadata>';
        $compressed = FontFile::fromBytes(FontBuilder::woff($tables, metadata: $metadata));

        self::assertSame(Format::Woff, $compressed->format);
        self::assertSame('Woff Sans', $compressed->face()->family());
        self::assertSame(300, $compressed->face()->weight);
        self::assertSame(Outlines::TrueType, $compressed->face()->outlines);
        self::assertSame($metadata, $compressed->extendedMetadata);

        $stored = FontFile::fromBytes(FontBuilder::woff($tables, compress: false));
        self::assertSame('Woff Sans', $stored->face()->family());
        self::assertNull($stored->extendedMetadata);
    }

    public function testRefusesACorruptWoffBlock(): void
    {
        $bytes = FontBuilder::woff(['name' => FontBuilder::names([1 => 'Woff Sans Corrupt Name Long Enough'])]);
        $corrupt = substr_replace($bytes, 'XXXX', 64, 4);

        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('A compressed WOFF block is corrupt or longer than it declares.');

        FontFile::fromBytes($corrupt);
    }

    public function testRefusesAWoffBlockLongerThanItsTable(): void
    {
        // The original length of the table, at 12 in its directory entry, made one byte shorter than what is stored.
        $bytes = FontBuilder::woff(['name' => FontBuilder::names([1 => 'Stored'])], compress: false);
        $bytes = substr_replace($bytes, FontBuilder::u32(\strlen(FontBuilder::names([1 => 'Stored'])) - 1), 44 + 12, 4);

        $this->expectExceptionMessageIsOrContains('A compressed WOFF block is corrupt or longer than it declares.');

        FontFile::fromBytes($bytes);
    }

    public function testRefusesAWoffBlockThatInflatesToAnotherLength(): void
    {
        $name = FontBuilder::names([1 => 'A long enough name to compress well, well, well, well']);
        $bytes = FontBuilder::woff(['name' => $name]);
        $bytes = substr_replace($bytes, FontBuilder::u32(\strlen($name) + 10), 44 + 12, 4);

        $this->expectExceptionMessageIsOrContains('A compressed WOFF block is corrupt or longer than it declares.');

        FontFile::fromBytes($bytes);
    }

    public function testRefusesAWoffBlockThatInflatesPastItsLength(): void
    {
        $name = FontBuilder::names([1 => 'A long enough name to compress well, well, well, well']);
        $bytes = FontBuilder::woff(['name' => $name]);
        $bytes = substr_replace($bytes, FontBuilder::u32(\strlen($name) - 1), 44 + 12, 4);

        $this->expectExceptionMessageIsOrContains('A compressed WOFF block is corrupt or longer than it declares.');

        FontFile::fromBytes($bytes);
    }

    public function testReadsAWoff2FileThroughTheDecompressorGiven(): void
    {
        $calls = [];
        $brotli = static function (string $data, int $length) use (&$calls): string {
            $calls[] = [$data, $length];

            return $data;
        };
        $name = FontBuilder::names([1 => 'Woff2 Sans']);
        $os2 = FontBuilder::os2(weight: 600);
        $bytes = FontBuilder::woff2([
            ['glyf', 'GLYF', 0],
            ['loca', '', 0],
            ['hmtx', 'HM', 1],
            ['name', $name, 0],
            ['OS/2', $os2, 0],
            ['zzzz', 'own', 0],
        ], metadata: '<metadata/>');
        $file = FontFile::fromBytes($bytes, $brotli);

        self::assertSame(Format::Woff2, $file->format);
        self::assertSame('Woff2 Sans', $file->face()->family());
        self::assertSame(600, $file->face()->weight);
        self::assertSame(Outlines::TrueType, $file->face()->outlines);
        self::assertSame('<metadata/>', $file->extendedMetadata);
        self::assertSame([['GLYFHM'.$name.$os2.'own', 6 + \strlen($name) + \strlen($os2) + 3], ['<metadata/>', 11]], $calls);
    }

    public function testReadsTheNullTransformOfGlyfAndTheExplicitTags(): void
    {
        $name = FontBuilder::names([1 => 'Plain']);
        $file = FontFile::fromBytes(FontBuilder::woff2([['glyf', 'G', 3], ['loca', 'LL', 3], ['Sill', 'S', 0], ['name', $name, 0, true]]), static fn (string $data): string => $data);

        self::assertSame('Plain', $file->face()->family());
        self::assertNull($file->extendedMetadata);
    }

    public function testRefusesWoff2WithoutADecompressor(): void
    {
        $this->expectException(UnsupportedFont::class);
        $this->expectExceptionMessageIsOrContains('A WOFF2 file compresses its tables with Brotli, which PHP does not decode without ext-brotli: install ext-brotli and pass Brotli::decompressor(), or read the WOFF, TrueType or OpenType file of the font.');

        FontFile::fromBytes(FontBuilder::woff2([['name', FontBuilder::names([]), 0]]));
    }

    public function testRefusesAWoff2Collection(): void
    {
        $this->expectException(UnsupportedFont::class);
        $this->expectExceptionMessageIsOrContains('WOFF2 collections are not read.');

        FontFile::fromBytes(FontBuilder::woff2([['name', FontBuilder::names([]), 0]], flavor: 'ttcf'), static fn (string $data): string => $data);
    }

    /**
     * @return iterable<string, array{Closure(string, int): (string|false), ?string}>
     */
    public static function failingDecompressors(): iterable
    {
        yield 'false' => [static fn (string $data): false => false, null];
        yield 'too short' => [static fn (string $data): string => substr($data, 1), null];
        yield 'too long' => [static fn (string $data): string => $data.'x', null];
        yield 'metadata too short' => [static fn (string $data): string => '<m/>' === $data ? '<m' : $data, '<m/>'];
    }

    /**
     * @param Closure(string, int): (string|false) $brotli
     */
    #[DataProvider('failingDecompressors')]
    public function testRefusesABrotliBlockOfTheWrongLength(Closure $brotli, ?string $metadata): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('A Brotli block of the WOFF2 file is corrupt or not as long as it declares.');

        FontFile::fromBytes(FontBuilder::woff2([['name', FontBuilder::names([1 => 'N']), 0]], $metadata), $brotli);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notFonts(): iterable
    {
        yield 'short' => ["\0\1\0\0".str_repeat("\0", 7), 'A font file is at least 12 bytes long.'];
        yield 'unknown' => ['GIF89a'.str_repeat("\0", 10), 'The file is neither TrueType, OpenType, a collection, WOFF nor WOFF2.'];
        yield 'table out of the file' => ["\0\1\0\0".FontBuilder::u16(1).str_repeat("\0", 6).'name'.FontBuilder::u32(0).FontBuilder::u32(28).FontBuilder::u32(1), 'The font file is truncated.'];
        yield 'no name' => [FontBuilder::sfnt(['head' => FontBuilder::head()]), 'The font has no "name" table.'];
    }

    #[DataProvider('notFonts')]
    public function testRefusesWhatIsNotAFont(string $bytes, string $message): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains($message);

        FontFile::fromBytes($bytes);
    }

    public function testReadsAFileFromItsPath(): void
    {
        $path = sys_get_temp_dir().'/phil-font-'.bin2hex(random_bytes(6)).'.ttf';
        file_put_contents($path, FontBuilder::sfnt(['name' => FontBuilder::names([4 => 'From Disk'])]));

        try {
            self::assertSame('From Disk', FontFile::fromFile($path)->face()->names->get(NameId::FullName));
        } finally {
            unlink($path);
        }
    }

    public function testRefusesAPathThatIsNoReadableFile(): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The font file "%s" cannot be read.', sys_get_temp_dir()));

        FontFile::fromFile(sys_get_temp_dir());
    }
}
