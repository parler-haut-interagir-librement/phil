<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator;

use Phil\Passwords\Exception\InvalidWordlist;
use Phil\Passwords\Generator\Wordlist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wordlist::class)]
final class WordlistTest extends TestCase
{
    public function testTheBundledLists(): void
    {
        foreach (['en' => Wordlist::english(), 'fr' => Wordlist::french(), 'es' => Wordlist::spanish(), 'de' => Wordlist::german()] as $language => $list) {
            self::assertSame($language, $list->name);
            self::assertGreaterThanOrEqual(Wordlist::MIN_WORDS, $list->count());
            self::assertSame($list->words, array_values(array_unique($list->words)));
            self::assertSame([], array_values(array_filter($list->words, static fn (string $word): bool => 1 !== preg_match('/^[a-z]{3,10}$/', $word))));
            self::assertEqualsWithDelta(log($list->count(), 2), $list->bitsPerWord(), 1e-12);
            // Lowercase words, each once: capitalised, each still gives a form of its own.
            self::assertSame($list->bitsPerWord(), $list->bitsPerCapitalisedWord());
            self::assertSame($list->bitsPerWord() + 1.0, $list->bitsPerRandomlyCapitalisedWord());
            self::assertSame($list->bitsPerCapitalisedWord(), Wordlist::of('copy', $list->words)->bitsPerCapitalisedWord());
            self::assertSame($list->bitsPerRandomlyCapitalisedWord(), Wordlist::of('copy', $list->words)->bitsPerRandomlyCapitalisedWord());
            // Built-in lists are valid custom lists too.
            self::assertSame($list->words, Wordlist::of('copy', $list->words)->words);
        }

        self::assertSame(Wordlist::LANGUAGES, ['en', 'fr', 'es', 'de']);
        self::assertSame('fr', Wordlist::bundled('fr')->name);
    }

    public function testAnUnknownLanguage(): void
    {
        $this->expectExceptionObject(new InvalidWordlist('No wordlist is bundled for the language "it".'));

        Wordlist::bundled('it');
    }

    public function testACustomList(): void
    {
        $words = $this->words(256);
        $list = Wordlist::of('site', $words);

        self::assertSame('site', $list->name);
        self::assertSame($words, $list->words);
        self::assertSame(256, $list->count());
        self::assertSame(8.0, $list->bitsPerWord());
        self::assertSame(['123', ...\array_slice($words, 1)], Wordlist::of('digits', ['123', ...\array_slice($words, 1)])->words);
        self::assertSame(str_repeat('é', 32), Wordlist::of('long', [str_repeat('é', 32), ...\array_slice($words, 1)])->words[0]);
    }

    public function testTheEntropyOfACustomList(): void
    {
        $list = Wordlist::of('site', $this->words(256));

        self::assertSame(8.0, $list->bitsPerCapitalisedWord());
        self::assertSame(9.0, $list->bitsPerRandomlyCapitalisedWord());

        $list = Wordlist::of('twins', ['w1', 'W1', ...\array_slice($this->words(256), 2)]);

        self::assertSame(8.0 - 2 / 256, $list->bitsPerCapitalisedWord());
        // Of the 512 equally likely draws, `W1` comes three times: from `w1` capitalised and from `W1` either way.
        self::assertEqualsWithDelta(9.0 - 3 * log(3, 2) / 512, $list->bitsPerRandomlyCapitalisedWord(), 1e-12);
    }

    public function testCapitalise(): void
    {
        self::assertSame('Word', Wordlist::capitalise('word'));
        self::assertSame('Élan', Wordlist::capitalise('élan'));
        self::assertSame('Word', Wordlist::capitalise('Word'));
        self::assertSame('123', Wordlist::capitalise('123'));
    }

    public function testTooFewWords(): void
    {
        $this->expectExceptionObject(new InvalidWordlist('The list "site" has 255 words; a passphrase draws from at least 256.'));

        Wordlist::of('site', $this->words(255));
    }

    public function testARepeatedWord(): void
    {
        $this->expectExceptionObject(new InvalidWordlist('The word "w1" is repeated in the list "site".'));

        Wordlist::of('site', [...$this->words(256), 'w1']);
    }

    public function testInvalidWords(): void
    {
        foreach (['', 'two words', "tab\tword", str_repeat('a', 33)] as $word) {
            try {
                Wordlist::of('site', [...$this->words(256), $word]);
                self::fail($word);
            } catch (InvalidWordlist $exception) {
                self::assertSame('The words of the list "site" have 1 to 32 characters and no whitespace.', $exception->getMessage());
            }
        }
    }

    public function testAWordWithASpaceOfAnotherScript(): void
    {
        $this->expectExceptionObject(new InvalidWordlist('The words of the list "site" have 1 to 32 characters and no whitespace.'));

        Wordlist::of('site', [...self::words(256), "em\u{2003}space"]);
    }

    /**
     * @return list<string>
     */
    private function words(int $count): array
    {
        return array_map(static fn (int $i): string => 'w'.$i, range(1, $count));
    }
}
