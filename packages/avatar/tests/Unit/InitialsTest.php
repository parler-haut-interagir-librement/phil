<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Initials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Initials::class)]
#[CoversClass(InvalidAvatar::class)]
final class InitialsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string, bool}>
     */
    public static function names(): iterable
    {
        yield 'two words' => ['Ada Lovelace', 2, 'AL', false];
        yield 'lowercase' => ['ada lovelace', 2, 'AL', false];
        yield 'the first and last of four words' => ['Jean-Luc de la Tour', 2, 'JT', false];
        yield 'three letters' => ['Jean-Luc de la Tour', 3, 'JLT', false];
        yield 'one letter' => ['Ada Lovelace', 1, 'A', false];
        yield 'one word' => ['Ada', 2, 'A', false];
        yield 'two words for three letters' => ['Ada Lovelace', 3, 'AL', false];
        yield 'spaces and punctuation around' => ["  «Ada»\t(Lovelace)  ", 2, 'AL', false];
        yield 'an apostrophe' => ["Mary O'Brien", 2, 'MB', false];
        yield 'a no-break space' => ["Ada\u{00A0}Lovelace", 2, 'AL', false];
        yield 'a precomposed accent' => ['émile zola', 2, 'ÉZ', false];
        yield 'a combining accent kept with its letter' => ["e\u{0301}mile zola", 2, "E\u{0301}Z", false];
        yield 'a letter uppercased one for one' => ['ßtraße', 2, 'ß', false];
        yield 'a titlecase digraph' => ['ǆemal bijedić', 2, 'ǄB', false];
        yield 'a digit' => ['3M company', 2, '3C', false];
        yield 'a leading symbol skipped' => ['😀Ada', 2, 'A', false];
        yield 'Greek' => ['σωκράτης παπαδόπουλος', 2, 'ΣΠ', false];
        yield 'Cyrillic' => ['анна каренина', 2, 'АК', false];
        yield 'Devanagari with its vowel sign' => ['किरण देसाई', 2, 'किदे', false];
        yield 'Han' => ['山田太郎', 2, '山', false];
        yield 'Han with a space' => ['山田 太郎', 2, '山', false];
        yield 'Hiragana' => ['さくら', 2, 'さ', false];
        yield 'Katakana' => ['サクラ たろう', 2, 'サ', false];
        yield 'Hangul' => ['김민준', 2, '김', false];
        yield 'Latin before Han' => ['Lee 山田', 2, 'L山', false];
        yield 'Arabic' => ['محمد علي', 2, 'مع', true];
        yield 'Hebrew with a maqaf' => ['דוד בן־גוריון', 2, 'דג', true];
        yield 'Persian with a zero-width non-joiner' => ["علی\u{200C}رضا", 1, 'ع', true];
        yield 'Latin and Arabic' => ['Ada علي', 2, 'Aع', true];
    }

    #[DataProvider('names')]
    public function testTheInitialsOfAName(string $name, int $max, string $letters, bool $rightToLeft): void
    {
        $initials = Initials::of($name, $max);

        self::assertSame($letters, $initials->letters);
        self::assertSame($rightToLeft, $initials->isRightToLeft());
        self::assertFalse($initials->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesWithoutInitials(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ["  \t\n"];
        yield 'emoji only' => ['😀 🎉'];
        yield 'punctuation only' => ['-- . --'];
        yield 'invalid UTF-8' => ["Ada \xC3\x28 Lovelace"];
        yield 'a lone combining mark' => ["\u{0301}"];
    }

    #[DataProvider('namesWithoutInitials')]
    public function testANameWithoutLettersHasNoInitials(string $name): void
    {
        $initials = Initials::of($name);

        self::assertSame('', $initials->letters);
        self::assertTrue($initials->isEmpty());
        self::assertFalse($initials->isRightToLeft());
    }

    public function testALongNameIsReadFromItsFirstKilobyte(): void
    {
        $name = 'Ada '.str_repeat('é', 600).' Lovelace';

        self::assertSame('AÉ', Initials::of($name)->letters);
        self::assertSame('AL', Initials::of('Ada '.str_repeat('x', 1011).' Lovelace')->letters);
        self::assertSame('AX', Initials::of('Ada '.str_repeat('x', 1020).' Lovelace')->letters);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidMaxima(): iterable
    {
        yield 'zero' => [0];
        yield 'four' => [4];
    }

    #[DataProvider('invalidMaxima')]
    public function testTheNumberOfLettersIsBounded(int $max): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('Initials hold 1 to 3 letters.'));

        Initials::of('Ada Lovelace', $max);
    }
}
