<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Override;
use Phil\Fonts\Exception\UnsupportedFont;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\Exception\FontUnreadable;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontFiles::class)]
#[CoversClass(FontUnreadable::class)]
final class FontFilesTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testReadsWoff2OnlyWithADecompressor(): void
    {
        self::assertFalse(new FontFiles()->readsWoff2());
        self::assertTrue(new FontFiles(str_pad(...))->readsWoff2());
        self::assertSame(\function_exists('brotli_uncompress'), FontFiles::withAvailableBrotli()->readsWoff2());
    }

    public function testReadsAFileAndBytes(): void
    {
        $directory = Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans')]);

        self::assertSame('Example Sans', new FontFiles()->read($directory.'/Sans.ttf')->face()->family());
        self::assertSame(Format::Woff2, new FontFiles(str_pad(...))->readBytes(Fonts::woff2('Example Sans'), 'Sans.woff2')->format);
    }

    public function testAWoff2FileWithoutBrotliSaysWhatToDo(): void
    {
        $directory = Directory::with(['fonts/Sans.woff2' => Fonts::woff2('Example Sans')]);

        try {
            new FontFiles()->read($directory.'/fonts/Sans.woff2');
            self::fail('A WOFF2 file is not read without a decompressor.');
        } catch (FontUnreadable $fontUnreadable) {
            self::assertSame('The font file "Sans.woff2" cannot be read: '.UnsupportedFont::woff2WithoutBrotli()->getMessage(), $fontUnreadable->getMessage());
            self::assertInstanceOf(UnsupportedFont::class, $fontUnreadable->getPrevious());
            self::assertSame(0, $fontUnreadable->getCode());
        }
    }

    public function testBytesThatAreNoFontNameTheFile(): void
    {
        $this->expectException(FontUnreadable::class);
        $this->expectExceptionMessageIsOrContains('The font file "upload.ttf" cannot be read: The file is neither TrueType, OpenType, a collection, WOFF nor WOFF2.');

        new FontFiles()->readBytes('not a font at all', 'upload.ttf');
    }
}
