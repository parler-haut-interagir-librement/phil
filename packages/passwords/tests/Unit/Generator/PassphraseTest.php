<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator;

use Phil\Passwords\Exception\InvalidPolicy;
use Phil\Passwords\Generator\Capitalization;
use Phil\Passwords\Generator\PassphraseGenerator;
use Phil\Passwords\Generator\PassphrasePolicy;
use Phil\Passwords\Generator\Wordlist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(PassphraseGenerator::class)]
#[CoversClass(PassphrasePolicy::class)]
final class PassphraseTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $policy = new PassphrasePolicy();
        $passphrase = new PassphraseGenerator()->generate();
        $words = explode('-', $passphrase);

        self::assertSame([7, '-', Capitalization::None, false], [$policy->words, $policy->separator, $policy->capitalization, $policy->includeDigit]);
        self::assertCount(7, $words);
        self::assertSame([], array_diff($words, Wordlist::english()->words));
        self::assertEqualsWithDelta(7 * log(Wordlist::english()->count(), 2), new PassphraseGenerator()->entropyBits(), 1e-9);
    }

    public function testWithers(): void
    {
        $policy = new PassphrasePolicy()->withWords(3)->withSeparator(' ')->withCapitalization(Capitalization::Words)->withDigit();

        self::assertSame([3, ' ', Capitalization::Words, true], [$policy->words, $policy->separator, $policy->capitalization, $policy->includeDigit]);
        self::assertFalse($policy->withDigit(false)->includeDigit);
    }

    public function testCapitalisationAndADigit(): void
    {
        $list = $this->list();
        $generate = static fn (PassphrasePolicy $policy, int $seed = 5): string => new PassphraseGenerator($list, new Randomizer(new Mt19937($seed)))->generate($policy);

        self::assertMatchesRegularExpression('/^w[a-z]+(\.w[a-z]+){3}$/', $generate(new PassphrasePolicy(4, '.')));
        self::assertMatchesRegularExpression('/^W[a-z]+( w[a-z]+){3}$/', $generate(new PassphrasePolicy(4, ' ', Capitalization::First)));
        self::assertMatchesRegularExpression('/^W[a-z]+(W[a-z]+){3}$/', $generate(new PassphrasePolicy(4, '', Capitalization::Words)));
        self::assertMatchesRegularExpression('/^(w|W)[a-z]+(-(w|W)[a-z]+){3}$/', $generate(new PassphrasePolicy(4, '-', Capitalization::Random)));

        $mixed = 0;

        for ($seed = 0; $seed < 20; ++$seed) {
            $words = explode('-', $generate(new PassphrasePolicy(8, '-', Capitalization::Random), $seed));
            $upper = \count(array_filter($words, static fn (string $word): bool => 'W' === $word[0]));
            $mixed += $upper > 0 && $upper < 8 ? 1 : 0;
        }

        self::assertGreaterThan(15, $mixed);

        $withDigit = $generate(new PassphrasePolicy(3, '-', includeDigit: true));
        self::assertSame(1, preg_match_all('/\d/', $withDigit));
        self::assertMatchesRegularExpression('/^(w[a-z]+\d?)(-w[a-z]+\d?){2}$/', $withDigit);

        $positions = [];

        for ($seed = 0; $seed < 30; ++$seed) {
            foreach (explode('-', $generate(new PassphrasePolicy(3, '-', includeDigit: true), $seed)) as $index => $word) {
                if (1 === preg_match('/\d$/', $word)) {
                    $positions[$index] = true;
                }
            }
        }

        ksort($positions);
        self::assertSame([0, 1, 2], array_keys($positions));
    }

    public function testEveryWordCanBeDrawn(): void
    {
        $list = $this->list();
        $generator = new PassphraseGenerator($list, new Randomizer(new Mt19937(11)));
        $seen = [];

        for ($i = 0; $i < 400; ++$i) {
            foreach (explode('-', $generator->generate(new PassphrasePolicy(10))) as $word) {
                $seen[$word] = true;
            }
        }

        self::assertCount(256, $seen);
        self::assertArrayHasKey($list->words[0], $seen);
        self::assertArrayHasKey($list->words[255], $seen);
    }

    public function testEntropy(): void
    {
        $generator = new PassphraseGenerator($this->list());

        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4)));
        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Words)));
        self::assertSame(36.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)));
        self::assertEqualsWithDelta(32.0 + log(40, 2), $generator->entropyBits(new PassphrasePolicy(4, includeDigit: true)), 1e-12);
    }

    public function testCapitalisationAddsNoEntropyToWordsWithoutCase(): void
    {
        $generator = new PassphraseGenerator(Wordlist::of('digits', array_map(strval(...), range(1000, 1255))));

        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)));
        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Words)));
        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::First)));

        $generator = new PassphraseGenerator(Wordlist::of('hebrew', array_map(static fn (int $i): string => 'א'.mb_chr(0x05D0 + intdiv($i, 16)).mb_chr(0x05D0 + $i % 16), range(0, 255))));

        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)));
    }

    public function testCapitalisationAddsNoEntropyToWordsAlreadyCapitalised(): void
    {
        $generator = new PassphraseGenerator(Wordlist::of('names', array_map(ucfirst(...), $this->list()->words)));

        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)));
        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Words)));
    }

    public function testWordsThatDifferOnlyByCaseShareTheirCapitalisedForm(): void
    {
        $lower = \array_slice($this->list()->words, 0, 128);
        $generator = new PassphraseGenerator(Wordlist::of('twins', [...$lower, ...array_map(ucfirst(...), $lower)]));

        // Capitalised, each pair gives one form: 128 forms, 7 bits.
        self::assertSame(32.0, $generator->entropyBits(new PassphrasePolicy(4)));
        self::assertSame(28.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Words)));
        self::assertSame(31.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::First)));
        // At random, of the 512 equally likely draws, each pair gives its lowercase form once and its capitalised form
        // three times.
        self::assertEqualsWithDelta(4 * (9 - 0.75 * log(3, 2)), $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)), 1e-12);
    }

    public function testOnlyTheWordsWithCaseAddTheirBit(): void
    {
        $words = [...array_map(strval(...), range(1000, 1127)), ...\array_slice($this->list()->words, 0, 128)];
        $generator = new PassphraseGenerator(Wordlist::of('mixed', $words));

        self::assertSame(34.0, $generator->entropyBits(new PassphrasePolicy(4, '', Capitalization::Random)));
    }

    public function testTheBoundsOfThePolicy(): void
    {
        self::assertSame(3, new PassphrasePolicy(3)->words);
        self::assertSame(64, new PassphrasePolicy(64)->words);
        self::assertSame('12345678', new PassphrasePolicy(separator: '12345678')->separator);
        self::assertSame('', new PassphrasePolicy(separator: '')->separator);

        foreach ([2, 65] as $words) {
            try {
                new PassphrasePolicy($words);
                self::fail((string) $words);
            } catch (InvalidPolicy $exception) {
                self::assertSame('A generated passphrase has 3 to 64 words.', $exception->getMessage());
            }
        }

        foreach (['123456789', 'x', '-é-'] as $separator) {
            try {
                new PassphrasePolicy(separator: $separator);
                self::fail($separator);
            } catch (InvalidPolicy $exception) {
                self::assertSame('The separator of a passphrase has at most 8 bytes and no letter.', $exception->getMessage());
            }
        }
    }

    public function testTheDrawsOfTheRandomizer(): void
    {
        $list = self::list();
        $policy = new PassphrasePolicy(5, '-', Capitalization::Random, includeDigit: true);
        $passphrase = new PassphraseGenerator($list, new Randomizer(new Mt19937(21)))->generate($policy);

        // The same draws, in the same order: a word and a coin for each, then the word that takes a digit and the digit.
        $randomizer = new Randomizer(new Mt19937(21));
        $words = [];

        for ($i = 0; $i < 5; ++$i) {
            $word = $list->words[$randomizer->getInt(0, 255)];
            $words[] = 1 === $randomizer->getInt(0, 1) ? ucfirst($word) : $word;
        }

        $words[$randomizer->getInt(0, 4)] .= (string) $randomizer->getInt(0, 9);

        self::assertSame(implode('-', $words), $passphrase);
    }

    public function testCapitalisationOfAccentedWords(): void
    {
        $words = array_map(static fn (int $i): string => 'élan'.strtr((string) $i, '0123456789', 'abcdefghij'), range(0, 255));
        $passphrase = new PassphraseGenerator(Wordlist::of('accents', $words), new Randomizer(new Mt19937(2)))->generate(new PassphrasePolicy(3, ' ', Capitalization::Words));

        self::assertMatchesRegularExpression('/^Élan[a-j]+ Élan[a-j]+ Élan[a-j]+$/u', $passphrase);
    }

    public function testASeparatorWithALetterOfAnotherScript(): void
    {
        $this->expectExceptionObject(new InvalidPolicy('The separator of a passphrase has at most 8 bytes and no letter.'));

        new PassphrasePolicy(separator: 'א');
    }

    private function list(): Wordlist
    {
        return Wordlist::of('test', array_map(static fn (int $i): string => 'w'.strtr((string) $i, '0123456789', 'abcdefghij'), range(0, 255)));
    }
}
