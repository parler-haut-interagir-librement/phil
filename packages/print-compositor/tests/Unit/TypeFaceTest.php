<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\Fonts\Exception\InvalidFont;
use Phil\PrintCompositor\Exception\UnsupportedFont;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\TypeFace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeFace::class)]
#[CoversClass(UnsupportedFont::class)]
final class TypeFaceTest extends TestCase
{
    public function testATrueTypeFileIsReadThroughPhilFonts(): void
    {
        $path = Fonts::trueType();
        $face = TypeFace::fromFile(\dirname($path).'/../'.basename(\dirname($path)).'/'.basename($path));

        self::assertSame($path, $face->path, 'The path is resolved, so FreeType finds the file wherever the process runs.');
        self::assertSame('Block Test', $face->font->family());
    }

    public function testTheCharactersAFontDoesNotCoverAreListedOnce(): void
    {
        $face = TypeFace::fromFile(Fonts::trueType());

        self::assertSame([], $face->missingCharacters("Café du\nmonde !"), 'The line break is not drawn; the space, "!" and "é" are covered.');
        self::assertSame(['ü', 'ß', '€'], $face->missingCharacters('Grüß € ü'), 'Each character once, in the order it first appears.');
    }

    public function testEachRunOfTheCharacterMapCounts(): void
    {
        $face = TypeFace::fromFile(Fonts::trueType());

        self::assertSame(
            ["\u{1F}", '"', '{', "\u{E8}", "\u{EA}"],
            $face->missingCharacters("\u{1F} !\"Azaz{\u{E8}é\u{EA}"),
            'The font covers U+0020 and U+0021, A to Z, a to z and U+00E9 among others: the code points on either side of a run are missing.',
        );
    }

    public function testAFileThatIsNotAFontIsRefused(): void
    {
        try {
            TypeFace::fromFile(__FILE__);
            self::fail('A PHP file is not a font.');
        } catch (UnsupportedFont $unsupportedFont) {
            self::assertInstanceOf(InvalidFont::class, $unsupportedFont->getPrevious());
            self::assertSame($unsupportedFont->getPrevious()->getMessage(), $unsupportedFont->getMessage());
        }
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(UnsupportedFont::class);
        $this->expectExceptionMessageIsOrContains('The font file "/nowhere/font.ttf" cannot be read.');

        TypeFace::fromFile('/nowhere/font.ttf');
    }

    public function testACollectionIsRefused(): void
    {
        $path = Fonts::collection();

        $this->expectException(UnsupportedFont::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The font file "%s" is a collection file; texts are drawn from a TrueType or OpenType file.', $path));

        TypeFace::fromFile($path);
    }

    public function testAWoffFileIsRefused(): void
    {
        $path = Fonts::woff();

        $this->expectException(UnsupportedFont::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The font file "%s" is a woff file; texts are drawn from a TrueType or OpenType file.', $path));

        TypeFace::fromFile($path);
    }
}
