<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Translation;

use InvalidArgumentException;
use Phil\BrailleExport\Translation\Braille;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Braille::class)]
final class BrailleTest extends TestCase
{
    public function testACellIsTheCodePointOfItsDots(): void
    {
        self::assertSame("\u{2800}", Braille::cell(''));
        self::assertSame("\u{2801}", Braille::cell('1'));
        self::assertSame("\u{281B}", Braille::cell('1245'));
        self::assertSame("\u{281B}", Braille::cell('5421'));
        self::assertSame("\u{28FF}", Braille::cell('12345678'));
        self::assertSame("\u{2880}", Braille::cell('8'));
        self::assertSame(Braille::BLANK, Braille::cell(''));
        self::assertSame(Braille::HYPHEN, Braille::cell('36'));
    }

    public function testASignOfSeveralCells(): void
    {
        self::assertSame("\u{2820}\u{2820}", Braille::cells('6', '6'));
        self::assertSame('', Braille::cells());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDots(): iterable
    {
        yield 'dot 0' => ['0'];
        yield 'dot 9' => ['9'];
        yield 'a dot twice' => ['11'];
        yield 'a letter' => ['1a'];
        yield 'a space' => ['1 2'];
        yield 'a line feed after' => ["1\n"];
        yield 'nine dots' => ['123456781'];
    }

    #[DataProvider('invalidDots')]
    public function testDotsAreChecked(string $dots): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('is not a set of dots from 1 to 8, each once.');

        Braille::cell($dots);
    }

    public function testBrailleIsRecognized(): void
    {
        self::assertTrue(Braille::isBraille(''));
        self::assertTrue(Braille::isBraille("\u{2800}\u{28FF}\u{2801}"));
        self::assertFalse(Braille::isBraille('a'));
        self::assertFalse(Braille::isBraille("\u{2801} "));
        self::assertFalse(Braille::isBraille("\u{2801}\n"));
        self::assertFalse(Braille::isBraille("\u{2900}"));
        self::assertFalse(Braille::isBraille("\u{27FF}"));
        self::assertFalse(Braille::isBraille("\xE2\xA0"));
    }

    public function testTheLengthCountsCells(): void
    {
        self::assertSame(0, Braille::length(''));
        self::assertSame(3, Braille::length("\u{2801}\u{2800}\u{28FF}"));
    }
}
