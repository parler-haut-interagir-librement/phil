<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator;

use Phil\Passwords\Exception\InvalidPolicy;
use Phil\Passwords\Generator\Capitalization;
use Phil\Passwords\Generator\PassphraseGenerator;
use Phil\Passwords\Generator\PassphrasePolicy;
use Phil\Passwords\Generator\Wordlist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A passphrase is generated, and its entropy counted, only when it gives back its draws: lists crafted so that two
 * draws write the same passphrase, and lists crafted so that they never do.
 */
#[CoversClass(PassphraseGenerator::class)]
final class PassphraseDecodingTest extends TestCase
{
    private const string SEPARATOR_MESSAGE = 'With the separator "%s", two different draws from the list "%s" can write the same passphrase, which would make it easier to guess than its entropy says: choose a separator that none of its words holds, or capitalise every word.';

    private const string DIGIT_MESSAGE = 'A digit appended to a word of the list "%s" can write another of its words, which would make the passphrase easier to guess than its entropy says: leave the digit out, or use a list whose words do not end with a digit.';

    /**
     * @return iterable<string, array{list<string>, PassphrasePolicy, bool}>
     */
    public static function policies(): iterable
    {
        // "wa" and "wb" run together into "wawb".
        $compound = [...self::words(), 'wawb'];

        yield 'a compound word, no separator' => [$compound, new PassphrasePolicy(4, ''), false];
        yield 'a compound word, a separator' => [$compound, new PassphrasePolicy(4, '-'), true];
        yield 'a compound word, every word capitalised' => [$compound, new PassphrasePolicy(4, '', Capitalization::Words), true];
        yield 'a compound word, the first word capitalised' => [$compound, new PassphrasePolicy(4, '', Capitalization::First), false];
        yield 'a compound word, capitalised at random' => [$compound, new PassphrasePolicy(4, '', Capitalization::Random), false];

        // "Wa", the capitalised form of "wa", and "wb" as it is run together into "Wawb".
        $capitalised = [...self::words(), 'Wawb'];

        yield 'a capitalised compound, as written' => [$capitalised, new PassphrasePolicy(4, ''), true];
        yield 'a capitalised compound, every word capitalised' => [$capitalised, new PassphrasePolicy(4, '', Capitalization::Words), true];
        yield 'a capitalised compound, the first word capitalised' => [$capitalised, new PassphrasePolicy(4, '', Capitalization::First), false];
        yield 'a capitalised compound, capitalised at random' => [$capitalised, new PassphrasePolicy(4, '', Capitalization::Random), false];

        // "wa", the separator and "wb" write "wa-wb".
        $hyphenated = [...self::words(), 'wa-wb'];

        yield 'a word holding the separator' => [$hyphenated, new PassphrasePolicy(4, '-'), false];
        yield 'a word holding another separator' => [$hyphenated, new PassphrasePolicy(4, '.'), true];
        yield 'a word holding part of the separator' => [$hyphenated, new PassphrasePolicy(4, '+-'), true];
        yield 'words holding part of a longer separator' => [[...self::words(), 'wa-', '-wb'], new PassphrasePolicy(4, '--'), false];

        // A digit appended to "wa" writes "wa1".
        $digit = [...self::words(), 'wa1'];

        yield 'a word that is another and a digit, without the digit' => [$digit, new PassphrasePolicy(4, '-'), true];
        yield 'a word that is another and a digit, with the digit' => [$digit, new PassphrasePolicy(4, '-', includeDigit: true), false];

        // "xy" is no word: the digit of "xy7" is its own.
        $ownDigit = [...self::words(), 'xy7'];

        yield 'a word ending with a digit of its own, a separator' => [$ownDigit, new PassphrasePolicy(4, '-', includeDigit: true), true];
        yield 'a word ending with a digit of its own, no separator' => [$ownDigit, new PassphrasePolicy(4, '', includeDigit: true), true];

        yield 'a separator holding a digit, with the digit' => [self::words(), new PassphrasePolicy(4, '1', includeDigit: true), true];
        yield 'a separator holding a digit, without the digit' => [self::words(), new PassphrasePolicy(4, '1'), true];

        // "x" and the digit "9" write "x9", as the digit 9 appended to "x" does; the other digits are no word.
        $lastDigit = [...self::words(), 'x', '9'];

        yield 'a digit that is a word, with the digit' => [$lastDigit, new PassphrasePolicy(4, '', includeDigit: true), false];

        // The word "wa-1" and the separator write "wa-1-"; "wa", the separator and the digit are not a word of it.
        $dashed = [...self::words(), 'wa-1'];

        yield 'a word holding the separator and a digit, with the digit' => [$dashed, new PassphrasePolicy(4, '-', includeDigit: true), true];

        // The digit comes between a word and the separator: "0wa" and "-" are not "0", "wa" and "-".
        $leading = [...self::words(), '0wa'];

        yield 'a word that starts with a digit, with the digit' => [$leading, new PassphrasePolicy(4, '-', includeDigit: true), true];

        // The digit comes between a word and the separator, and the separator holds a digit: the words "0a", "a", "0" and
        // "-0" write the same only if the digit follows the separator or a word follows the digit.
        $mixed = [...self::words(), '0a', 'a', '0', '-0'];

        yield 'words made of digits and dashes, a separator holding both' => [$mixed, new PassphrasePolicy(4, '0-', includeDigit: true), true];

        // Four digits each: 1000, then 1100 and a digit, write what 1000 and a digit, then 1001, write.
        $numbers = array_map(strval(...), range(1000, 1255));

        yield 'numbers, no separator' => [$numbers, new PassphrasePolicy(4, ''), true];
        yield 'numbers and a digit, no separator' => [$numbers, new PassphrasePolicy(4, '', includeDigit: true), false];
        yield 'numbers and a digit, a separator' => [$numbers, new PassphrasePolicy(4, '-', includeDigit: true), true];
    }

    /**
     * @param list<string> $words
     */
    #[DataProvider('policies')]
    public function testWhetherThePassphraseGivesBackItsDraws(array $words, PassphrasePolicy $policy, bool $accepted): void
    {
        $generator = new PassphraseGenerator(Wordlist::of('crafted', $words), new Randomizer(new Mt19937(3)));

        self::assertSame($accepted, $generator->accepts($policy));

        if ($accepted) {
            self::assertIsString($generator->generate($policy));
            self::assertGreaterThanOrEqual(32.0, $generator->entropyBits($policy));

            return;
        }

        foreach ([$generator->generate(...), $generator->entropyBits(...)] as $call) {
            try {
                $call($policy);
                self::fail('The policy is refused.');
            } catch (InvalidPolicy $exception) {
                self::assertStringContainsString('easier to guess than its entropy says', $exception->getMessage());
            }
        }
    }

    public function testTheMessages(): void
    {
        $compound = new PassphraseGenerator(Wordlist::of('compound', [...self::words(), 'wawb']));
        $digit = new PassphraseGenerator(Wordlist::of('digit', [...self::words(), 'wa1']));

        $this->assertRefused(\sprintf(self::SEPARATOR_MESSAGE, '', 'compound'), static fn (): string => $compound->generate(new PassphrasePolicy(4, '')));
        $this->assertRefused(\sprintf(self::SEPARATOR_MESSAGE, '', 'compound'), static fn (): float => $compound->entropyBits(new PassphrasePolicy(4, '')));
        $this->assertRefused(\sprintf(self::DIGIT_MESSAGE, 'digit'), static fn (): string => $digit->generate(new PassphrasePolicy(4, includeDigit: true)));
        $this->assertRefused(\sprintf(self::DIGIT_MESSAGE, 'digit'), static fn (): float => $digit->entropyBits(new PassphrasePolicy(4, includeDigit: true)));

        // Both at once: the digit is named first.
        $this->assertRefused(\sprintf(self::DIGIT_MESSAGE, 'digit'), static fn (): float => $digit->entropyBits(new PassphrasePolicy(4, '', includeDigit: true)));
        $this->assertRefused(\sprintf(self::SEPARATOR_MESSAGE, '', 'numbers'), static fn (): float => new PassphraseGenerator(Wordlist::of('numbers', array_map(strval(...), range(1000, 1255))))->entropyBits(new PassphrasePolicy(4, '', includeDigit: true)));
    }

    public function testTheEntropyOfTheAcceptedPolicies(): void
    {
        $ownDigit = new PassphraseGenerator(Wordlist::of('own', [...self::words(), 'xy7']));

        self::assertEqualsWithDelta(4 * log(257, 2) + log(40, 2), $ownDigit->entropyBits(new PassphrasePolicy(4, '-', includeDigit: true)), 1e-12);
        self::assertEqualsWithDelta(4 * log(257, 2), new PassphraseGenerator(Wordlist::of('compound', [...self::words(), 'wawb']))->entropyBits(new PassphrasePolicy(4, '', Capitalization::Words)), 1e-12);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bundled(): iterable
    {
        foreach (Wordlist::LANGUAGES as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('bundled')]
    public function testTheBundledLists(string $language): void
    {
        $list = Wordlist::bundled($language);
        $generator = new PassphraseGenerator($list, new Randomizer(new Mt19937(8)));
        $bits = 7 * log($list->count(), 2);

        // Compound words of the lists, such as "weekend" from "week" and "end", run together without a separator.
        self::assertFalse($generator->accepts(new PassphrasePolicy(separator: '')));
        self::assertFalse($generator->accepts(new PassphrasePolicy(separator: '', capitalization: Capitalization::First)));
        self::assertFalse($generator->accepts(new PassphrasePolicy(separator: '', capitalization: Capitalization::Random)));
        self::assertFalse($generator->accepts(new PassphrasePolicy(separator: '', includeDigit: true)));

        foreach (['-', '1', '--'] as $separator) {
            foreach (Capitalization::cases() as $capitalization) {
                self::assertTrue($generator->accepts(new PassphrasePolicy(7, $separator, $capitalization, true)), $separator);
                self::assertTrue($generator->accepts(new PassphrasePolicy(7, $separator, $capitalization)), $separator);
            }
        }

        $camel = new PassphrasePolicy(separator: '', capitalization: Capitalization::Words);
        self::assertTrue($generator->accepts($camel));
        self::assertTrue($generator->accepts($camel->withDigit()));
        self::assertEqualsWithDelta($bits, $generator->entropyBits($camel), 1e-9);
        self::assertEqualsWithDelta($bits, $generator->entropyBits(), 1e-9);
        self::assertMatchesRegularExpression('/^([A-Z][a-z]+){7}$/', $generator->generate($camel));
    }

    public function testTheEnglishCompoundWordsThatRunTogether(): void
    {
        $words = array_flip(Wordlist::english()->words);

        self::assertArrayHasKey('week', $words);
        self::assertArrayHasKey('end', $words);
        self::assertArrayHasKey('weekend', $words);
    }

    /**
     * @param callable(): mixed $call
     */
    private function assertRefused(string $message, callable $call): void
    {
        try {
            $call();
            self::fail('The policy is refused.');
        } catch (InvalidPolicy $invalidPolicy) {
            self::assertSame($message, $invalidPolicy->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private static function words(): array
    {
        return array_map(static fn (int $i): string => 'w'.strtr((string) $i, '0123456789', 'abcdefghij'), range(0, 255));
    }
}
