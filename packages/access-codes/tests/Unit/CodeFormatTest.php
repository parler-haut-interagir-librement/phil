<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Phil\AccessCodes\CodeAlphabet;
use Phil\AccessCodes\CodeFormat;
use Phil\AccessCodes\Exception\InvalidCode;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(CodeFormat::class)]
final class CodeFormatTest extends TestCase
{
    public function testTheDefaultIsTwelveSymbolsOfSixtyBitsAndACheckSymbol(): void
    {
        $format = new CodeFormat();

        self::assertSame(12, $format->length);
        self::assertTrue($format->checkSymbol);
        self::assertSame(4, $format->groupSize);
        self::assertSame(13, $format->codeLength());
        self::assertEqualsWithDelta(60.0, $format->entropyBits(), 1e-9);
        self::assertSame(CodeAlphabet::CROCKFORD, $format->alphabet->symbols);
    }

    public function testTheCheckSymbolIsLuhnModN(): void
    {
        $format = new CodeFormat();

        self::assertSame('7KQ2M9XDH4TBH', $format->parse('7KQ2M9XDH4TBH'));
        self::assertSame('0000000000000', $format->parse('0000000000000'));
        self::assertSame('ZZZZZZZZZZZZC', $format->parse('ZZZZZZZZZZZZC'));
        self::assertSame('01234567899', new CodeFormat(new CodeAlphabet('0123456789ABCDEF'), 10)->parse('01234567899'));
        self::assertSame('FEDCBA98761', new CodeFormat(new CodeAlphabet('0123456789ABCDEF'), 10)->parse('fedcba98761'));
    }

    public function testAHexadecimalFormatCountsItsCheckSymbolInItsBase(): void
    {
        $format = new CodeFormat(new CodeAlphabet('0123456789ABCDEF'), 10, groupSize: 0);
        $code = $format->generate(new Randomizer(new Xoshiro256StarStar(7)));

        self::assertSame($code, $format->parse(strtolower($code)));
    }

    public function testItReadsWhatAPersonTyped(): void
    {
        self::assertSame('7KQ2M9XDH4TBH', new CodeFormat()->parse("  7kq2-m9xd-h4tb-h\n"));
    }

    public function testItTakesLookalikesForTheirDigit(): void
    {
        self::assertSame('0000000000000', new CodeFormat()->parse('OOOO-oooo-0000-o'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mistyped(): iterable
    {
        yield 'one symbol changed' => ['7KQ2M9XDH4TCH', 'The check symbol of the code does not match: a symbol was mistyped.'];
        yield 'two neighbours swapped' => ['K7Q2M9XDH4TBH', 'The check symbol of the code does not match: a symbol was mistyped.'];
        yield 'the check symbol changed' => ['7KQ2M9XDH4TBJ', 'The check symbol of the code does not match: a symbol was mistyped.'];
        yield 'a symbol missing' => ['7KQ2M9XDH4TB', 'A code has 13 symbols; 12 were given.'];
        yield 'a symbol more' => ['7KQ2M9XDH4TBHH', 'A code has 13 symbols; 14 were given.'];
        yield 'nothing' => ['  - ', 'A code has 13 symbols; 0 were given.'];
        yield 'a U' => ['7KQ2M9XDH4TUH', 'The code holds a symbol outside its alphabet.'];
        yield 'punctuation' => ['7KQ2M9XDH4T.H', 'The code holds a symbol outside its alphabet.'];
        yield 'a letter outside ASCII' => ['7KQ2M9XDH4Té', 'The code holds a symbol outside its alphabet.'];
    }

    #[DataProvider('mistyped')]
    public function testItRefusesAMistypedCode(string $text, string $message): void
    {
        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CodeFormat()->parse($text);
    }

    public function testItRefusesTextLongerThanItReads(): void
    {
        self::assertSame('0000000000000', new CodeFormat()->parse('0000000000000'.str_repeat(' ', 243)));

        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains('A code is at most 256 characters as typed; 257 were given.');

        new CodeFormat()->parse('0000000000000'.str_repeat(' ', 244));
    }

    public function testWithoutACheckSymbolAnySymbolsOfTheLengthAreRead(): void
    {
        $format = new CodeFormat(checkSymbol: false);

        self::assertSame(12, $format->codeLength());
        self::assertSame('7KQ2M9XDH4TB', $format->parse('7KQ2M9XDH4TB'));
    }

    public function testItGeneratesCodesOfItsAlphabetThatReadBack(): void
    {
        $format = new CodeFormat();
        $randomizer = new Randomizer(new Xoshiro256StarStar(42));

        for ($i = 0; $i < 200; ++$i) {
            $code = $format->generate($randomizer);

            self::assertSame(13, \strlen($code));
            self::assertSame(13, strspn($code, CodeAlphabet::CROCKFORD));
            self::assertSame($code, $format->parse($code));
        }
    }

    public function testAGeneratedCodeWithoutCheckSymbolHasItsLength(): void
    {
        self::assertSame(12, \strlen(new CodeFormat(checkSymbol: false)->generate(new Randomizer(new Xoshiro256StarStar(1)))));
    }

    public function testItShowsACodeInGroups(): void
    {
        self::assertSame('7KQ2-M9XD-H4TB-H', new CodeFormat()->display('7KQ2M9XDH4TBH'));
        self::assertSame('7KQ2M9-XDH4TB-H', new CodeFormat(groupSize: 6)->display('7KQ2M9XDH4TBH'));
        self::assertSame('7KQ2M9XDH4TBH', new CodeFormat(groupSize: 0)->display('7KQ2M9XDH4TBH'));
    }

    public function testItIsBuiltFromTheBitsAskedFor(): void
    {
        self::assertSame(12, CodeFormat::withEntropy(60)->length);
        self::assertTrue(CodeFormat::withEntropy(60)->checkSymbol);
        self::assertSame(4, CodeFormat::withEntropy(60)->groupSize);
        self::assertSame(9, CodeFormat::withEntropy(41)->length);
        self::assertSame(10, CodeFormat::withEntropy(40, new CodeAlphabet('0123456789ABCDEF'))->length);

        $format = CodeFormat::withEntropy(80, null, false, 5);

        self::assertSame(16, $format->length);
        self::assertFalse($format->checkSymbol);
        self::assertSame(5, $format->groupSize);
    }

    public function testFortyBitsIsTheLeast(): void
    {
        self::assertSame(8, new CodeFormat(length: 8)->length);

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A code carries 40 bits of entropy at the least; this format carries 35.0.');

        new CodeFormat(length: 7);
    }

    public function testSixtyFourSymbolsIsTheMost(): void
    {
        self::assertSame(64, new CodeFormat(length: 64)->length);

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A code has 1 to 64 random symbols.');

        new CodeFormat(length: 65);
    }

    public function testOneSymbolIsTheLeastLength(): void
    {
        foreach ([0, -1] as $length) {
            try {
                new CodeFormat(length: $length);
                self::fail('A length of '.$length.' was accepted.');
            } catch (InvalidConfiguration $exception) {
                self::assertSame('A code has 1 to 64 random symbols.', $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('A code carries 40 bits of entropy at the least; this format carries 5.0.');

        new CodeFormat(length: 1);
    }

    public function testTheGroupsAreBounded(): void
    {
        self::assertSame(64, new CodeFormat(groupSize: 64)->groupSize);

        foreach ([-1, 65] as $size) {
            try {
                new CodeFormat(groupSize: $size);
                self::fail('A group of '.$size.' symbols was accepted.');
            } catch (InvalidConfiguration $exception) {
                self::assertSame('A group of a shown code has 0 to 64 symbols.', $exception->getMessage());
            }
        }
    }
}
