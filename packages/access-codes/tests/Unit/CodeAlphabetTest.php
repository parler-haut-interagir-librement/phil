<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Phil\AccessCodes\CodeAlphabet;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeAlphabet::class)]
final class CodeAlphabetTest extends TestCase
{
    public function testTheDefaultIsCrockfordsBase32(): void
    {
        $alphabet = new CodeAlphabet();

        self::assertSame('0123456789ABCDEFGHJKMNPQRSTVWXYZ', $alphabet->symbols);
        self::assertSame(32, $alphabet->size());
        self::assertEqualsWithDelta(5.0, $alphabet->bitsPerSymbol(), 1e-9);
    }

    public function testItReadsTextInCapitalsWithoutSeparatorsAndLookalikes(): void
    {
        self::assertSame('0110ABCZ', new CodeAlphabet()->normalize('oIl0-ab c z'));
    }

    public function testALookalikeInsideTheAlphabetStaysItself(): void
    {
        $alphabet = new CodeAlphabet('0123456789ABCDEFGHIJKLMNOPQRSTUV');

        self::assertSame('OIL', $alphabet->normalize('oil'));
    }

    public function testALookalikeIsTakenOnlyForADigitOfTheAlphabet(): void
    {
        self::assertSame('OIL', new CodeAlphabet('ABCDEFGHJKMNPQRSTVWXYZ')->normalize('oil'));
        self::assertSame('0IL', new CodeAlphabet('0ABCDEFGHJKMNPQRSTVWXYZ')->normalize('oil'));
        self::assertSame('O11', new CodeAlphabet('1ABCDEFGHJKMNPQRSTVWXYZ')->normalize('oil'));
    }

    public function testItFindsSymbolsByPosition(): void
    {
        $alphabet = new CodeAlphabet();

        self::assertSame(0, $alphabet->indexOf('0'));
        self::assertSame(31, $alphabet->indexOf('Z'));
        self::assertNull($alphabet->indexOf('U'));
        self::assertNull($alphabet->indexOf(''));
        self::assertSame('Z', $alphabet->symbolAt(31));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAlphabets(): iterable
    {
        yield 'too short' => ['0123456789ABCDE'];
        yield 'too long' => ['0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ0'];
        yield 'lowercase' => ['0123456789abcdef'];
        yield 'a letter before the symbols' => ['a0123456789ABCDEF'];
        yield 'punctuation' => ['0123456789ABCDE-'];
        yield 'a newline at the end' => ["0123456789ABCDEF\n"];
        yield 'a symbol twice' => ['0123456789ABCDEA'];
    }

    #[DataProvider('invalidAlphabets')]
    public function testItRefusesAnAlphabetOutOfBounds(string $symbols): void
    {
        $this->expectException(InvalidConfiguration::class);

        new CodeAlphabet($symbols);
    }

    public function testItAcceptsTheBoundsOfItsSize(): void
    {
        self::assertSame(16, new CodeAlphabet('0123456789ABCDEF')->size());
        self::assertSame(36, new CodeAlphabet('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ')->size());
    }

    public function testTheMessageNamesTheBounds(): void
    {
        $this->expectExceptionMessageIsOrContains('An alphabet of codes holds 16 to 36 digits and capital letters.');

        new CodeAlphabet('AB');
    }

    public function testTheMessageOfARepeatedSymbol(): void
    {
        $this->expectExceptionMessageIsOrContains('An alphabet of codes holds each symbol once.');

        new CodeAlphabet('0123456789ABCDEA');
    }
}
