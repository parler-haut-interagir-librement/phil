<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Exception\FontsException;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Reader\Binary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Binary::class)]
#[CoversClass(InvalidFont::class)]
final class BinaryTest extends TestCase
{
    public function testReadsBigEndianNumbers(): void
    {
        $binary = new Binary("\x12\x34\x56\x78\xFF\xFE\x80\x00\x00\x01");

        self::assertSame(10, $binary->length());
        self::assertSame(0x12, $binary->uint8(0));
        self::assertSame(0xFF, $binary->uint8(4));
        self::assertSame(0x1234, $binary->uint16(0));
        self::assertSame(0xFFFE, $binary->uint16(4));
        self::assertSame(0x12345678, $binary->uint32(0));
        self::assertSame(0xFFFE8000, $binary->uint32(4));
        self::assertSame(0x1234, $binary->int16(0));
        self::assertSame(-2, $binary->int16(4));
        self::assertSame(-32768, $binary->int16(6));
        self::assertSame(0x7FFF, new Binary("\x7F\xFF")->int16(0));
        self::assertSame("\x56\x78\xFF\xFE", $binary->tag(2));
        self::assertSame("\x00\x01", $binary->slice(8, 2));
        self::assertSame('', $binary->slice(10, 0));
    }

    public function testReadsSignedFixedPointNumbers(): void
    {
        self::assertSame(1.5, new Binary("\x00\x01\x80\x00")->fixed(0));
        self::assertSame(-12.0, new Binary("\xFF\xF4\x00\x00")->fixed(0));
        self::assertSame(-11.25, new Binary("\xFF\xF4\xC0\x00")->fixed(0));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'past the end' => [9, 2];
        yield 'negative offset' => [-1, 1];
        yield 'negative length' => [0, -1];
    }

    #[DataProvider('outOfBounds')]
    public function testRefusesAReadOutOfBounds(int $at, int $length): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('The font file is truncated.');

        new Binary(str_repeat("\0", 10))->slice($at, $length);
    }

    public function testEachNumberIsCheckedAgainstTheEnd(): void
    {
        $binary = new Binary("\0\0\0");

        foreach ([static fn (): int => $binary->uint16(2), static fn (): int => $binary->uint32(0), static fn (): int => $binary->uint8(3)] as $read) {
            try {
                $read();
                self::fail('A read past the end must throw.');
            } catch (FontsException $exception) {
                self::assertSame('The font file is truncated.', $exception->getMessage());
            }
        }
    }

    public function testReadsWoff2Base128Numbers(): void
    {
        self::assertSame([0, 1], new Binary("\x00")->base128(0));
        self::assertSame([0x7F, 2], new Binary("\xAA\x7F")->base128(1));
        self::assertSame([0x80, 2], new Binary("\x81\x00")->base128(0));
        self::assertSame([0x3FFF, 2], new Binary("\xFF\x7F")->base128(0));
        self::assertSame([0xFFFFFFFF, 5], new Binary("\x8F\xFF\xFF\xFF\x7F")->base128(0));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBase128(): iterable
    {
        yield 'leading zero byte' => ["\x80\x01", 'A WOFF2 number starts with a zero byte.'];
        yield 'over 32 bits' => ["\x90\x80\x80\x80\x00", 'A WOFF2 number does not fit in 32 bits.'];
        yield 'five continued bytes' => ["\x81\x81\x81\x81\x81\x00", 'A WOFF2 number is longer than five bytes.'];
        yield 'unfinished' => ["\x81", 'The font file is truncated.'];
    }

    #[DataProvider('invalidBase128')]
    public function testRefusesInvalidBase128Numbers(string $bytes, string $message): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Binary($bytes)->base128(0);
    }
}
