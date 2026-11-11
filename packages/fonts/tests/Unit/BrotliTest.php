<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Closure;
use Phil\Fonts\Brotli;
use Phil\Fonts\Exception\UnsupportedFont;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Brotli::class)]
#[CoversClass(UnsupportedFont::class)]
final class BrotliTest extends TestCase
{
    public function testTheDecompressorIsTheFunctionOfExtBrotli(): void
    {
        self::assertSame('brotli_uncompress', Brotli::FUNCTION);
        self::assertSame(\function_exists('brotli_uncompress'), Brotli::decompressor() instanceof Closure);
    }

    public function testADefinedFunctionBecomesTheDecompressor(): void
    {
        $decompressor = Brotli::decompressor('str_pad');

        self::assertNotNull($decompressor);
        self::assertSame('ab  ', $decompressor('ab', 4));
    }

    public function testWhatIsNotAStringIsAFailure(): void
    {
        $decompressor = Brotli::decompressor('str_word_count');

        self::assertNotNull($decompressor);
        self::assertFalse($decompressor('two words', 0));
    }

    public function testAnUndefinedFunctionGivesNone(): void
    {
        self::assertNull(Brotli::decompressor('phil_fonts_no_such_decompressor'));
    }

    public function testAWoff2FileReadsThroughTheDecompressor(): void
    {
        $name = FontBuilder::names([1 => 'Through']);
        $file = FontFile::fromBytes(FontBuilder::woff2([['name', $name, 0]]), Brotli::decompressor('str_pad'));

        self::assertSame(Format::Woff2, $file->format);
        self::assertSame('Through', $file->face()->family());
    }

    public function testTheMessageWithoutBrotliSaysWhatToDo(): void
    {
        self::assertSame(
            'A WOFF2 file compresses its tables with Brotli, which PHP does not decode without ext-brotli: install ext-brotli and pass Brotli::decompressor(), or read the WOFF, TrueType or OpenType file of the font.',
            UnsupportedFont::woff2WithoutBrotli()->getMessage(),
        );
    }
}
