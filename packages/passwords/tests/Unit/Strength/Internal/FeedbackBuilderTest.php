<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Dictionary;
use Phil\Passwords\Strength\Feedback;
use Phil\Passwords\Strength\Internal\FeedbackBuilder;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\Suggestion;
use Phil\Passwords\Strength\Warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedbackBuilder::class)]
final class FeedbackBuilderTest extends TestCase
{
    public function testAnEmptyPassword(): void
    {
        self::assertEquals(new Feedback(null, [Suggestion::UseWords, Suggestion::NoSymbolsNeeded]), FeedbackBuilder::build(Score::VeryWeak, []));
    }

    public function testAStrongPasswordGetsNoFeedback(): void
    {
        $sequence = [new PatternMatch(Pattern::Sequence, 0, 2, 'abc', 12.0)];

        self::assertTrue(FeedbackBuilder::build(Score::Strong, $sequence)->isEmpty());
        self::assertTrue(FeedbackBuilder::build(Score::VeryStrong, $sequence)->isEmpty());
        self::assertFalse(FeedbackBuilder::build(Score::Fair, $sequence)->isEmpty());
    }

    /**
     * @return iterable<string, array{PatternMatch, ?Warning, list<Suggestion>}>
     */
    public static function patterns(): iterable
    {
        yield 'straight row' => [new PatternMatch(Pattern::Spatial, 0, 5, 'qwerty', 1.0, turns: 1), Warning::StraightRow, [Suggestion::LongerKeyboardPattern]];
        yield 'keyboard pattern' => [new PatternMatch(Pattern::Spatial, 0, 5, 'qwedcx', 1.0, turns: 2), Warning::ShortKeyboardPattern, [Suggestion::LongerKeyboardPattern]];
        yield 'repeated character' => [new PatternMatch(Pattern::Repeat, 0, 3, 'éééé', 1.0, baseToken: 'é', repeatCount: 4), Warning::RepeatedCharacter, [Suggestion::AvoidRepeats]];
        yield 'repeated pattern' => [new PatternMatch(Pattern::Repeat, 0, 3, 'abab', 1.0, baseToken: 'ab', repeatCount: 2), Warning::RepeatedPattern, [Suggestion::AvoidRepeats]];
        yield 'sequence' => [new PatternMatch(Pattern::Sequence, 0, 2, 'abc', 1.0), Warning::Sequence, [Suggestion::AvoidSequences]];
        yield 'year' => [new PatternMatch(Pattern::Year, 0, 3, '2020', 1.0), Warning::RecentYear, [Suggestion::AvoidRecentYears]];
        yield 'date' => [new PatternMatch(Pattern::Date, 0, 5, '010120', 1.0), Warning::Date, [Suggestion::AvoidDates]];
        yield 'bruteforce' => [new PatternMatch(Pattern::Bruteforce, 0, 2, 'x7k', 1.0), null, []];
        yield 'top ten' => [self::word(Dictionary::PASSWORDS, 'qwerty', 10), Warning::TopTen, []];
        yield 'top hundred' => [self::word(Dictionary::PASSWORDS, 'monkey', 11), Warning::TopHundred, []];
        yield 'top hundred at its bound' => [self::word(Dictionary::PASSWORDS, 'monkey', 100), Warning::TopHundred, []];
        yield 'common password' => [self::word(Dictionary::PASSWORDS, 'whatever', 101), Warning::CommonPassword, []];
        yield 'common password with substitutions' => [self::word(Dictionary::PASSWORDS, 'p@ss', 3, substitutions: ['@' => 'a']), Warning::SimilarToCommon, [Suggestion::Substitutions]];
        yield 'common password reversed' => [self::word(Dictionary::PASSWORDS, 'drowssap', 1, reversed: true), Warning::SimilarToCommon, [Suggestion::ReversedWords]];
        yield 'short reversed word' => [self::word(Dictionary::ENGLISH, 'tac', 50, reversed: true), Warning::SingleWord, []];
        yield 'reversed word of four letters' => [self::word(Dictionary::ENGLISH, 'egap', 50, reversed: true), Warning::SingleWord, [Suggestion::ReversedWords]];
        yield 'name' => [self::word(Dictionary::NAMES, 'marie', 5), Warning::Name, []];
        yield 'surname' => [self::word(Dictionary::SURNAMES, 'tremblay', 5), Warning::Name, []];
        yield 'user input' => [self::word(Dictionary::USER_INPUTS, 'jdupont', 1), Warning::UserInput, [Suggestion::AvoidPersonalInformation]];
        yield 'word' => [self::word(Dictionary::FRENCH, 'soleil', 1), Warning::SingleWord, []];
        yield 'capitalised word' => [self::word(Dictionary::FRENCH, 'Soleil', 1), Warning::SingleWord, [Suggestion::Capitalization]];
        yield 'word in uppercase' => [self::word(Dictionary::FRENCH, 'SOLEIL', 1), Warning::SingleWord, [Suggestion::AllUppercase]];
        yield 'word in uppercase with digits' => [self::word(Dictionary::PASSWORDS, 'ABC123', 1), Warning::TopTen, [Suggestion::AllUppercase]];
        yield 'digits only' => [self::word(Dictionary::PASSWORDS, '123456', 1), Warning::TopTen, []];
        yield 'mixed case' => [self::word(Dictionary::FRENCH, 'SoLeil', 1), Warning::SingleWord, []];
        yield 'capitalised accented word' => [self::word(Dictionary::FRENCH, 'Été', 1), Warning::SingleWord, [Suggestion::Capitalization]];
        yield 'accented word in uppercase' => [self::word(Dictionary::FRENCH, 'ÉTÉ', 1), Warning::SingleWord, [Suggestion::AllUppercase]];
        yield 'uppercase word of another script' => [self::word(Dictionary::ENGLISH, 'ḂLE', 1), Warning::SingleWord, [Suggestion::AllUppercase]];
        yield 'uppercase word without an ASCII letter' => [self::word(Dictionary::FRENCH, 'ÉÀÈ', 1), Warning::SingleWord, [Suggestion::AllUppercase]];
        yield 'short reversed accented word' => [self::word(Dictionary::FRENCH, 'çaé', 50, reversed: true), Warning::SingleWord, []];
        yield 'last letter in uppercase' => [self::word(Dictionary::FRENCH, 'soleiL', 1), Warning::SingleWord, []];
    }

    /**
     * @param list<Suggestion> $suggestions
     */
    #[DataProvider('patterns')]
    public function testThePatternAlone(PatternMatch $match, ?Warning $warning, array $suggestions): void
    {
        self::assertEquals(new Feedback($warning, [Suggestion::AddWord, ...$suggestions]), FeedbackBuilder::build(Score::Weak, [$match]));
    }

    public function testTheLongestPatternAmongOthers(): void
    {
        $sequence = [
            new PatternMatch(Pattern::Bruteforce, 0, 0, 'x', 11.0),
            new PatternMatch(Pattern::Sequence, 1, 3, 'abc', 12.0),
            self::word(Dictionary::PASSWORDS, 'qwerty', 1, start: 4),
            self::word(Dictionary::NAMES, 'zoe', 1, start: 10),
            new PatternMatch(Pattern::Year, 13, 16, '2020', 20.0),
        ];

        self::assertEquals(new Feedback(Warning::SimilarToCommon, [Suggestion::AddWord]), FeedbackBuilder::build(Score::Fair, $sequence));
    }

    public function testNamesAndWordsAmongOthers(): void
    {
        $tail = new PatternMatch(Pattern::Bruteforce, 6, 6, '!', 11.0);

        self::assertEquals(new Feedback(Warning::CommonName, [Suggestion::AddWord]), FeedbackBuilder::build(Score::Weak, [self::word(Dictionary::NAMES, 'marie', 5), $tail]));
        self::assertEquals(new Feedback(null, [Suggestion::AddWord]), FeedbackBuilder::build(Score::Weak, [self::word(Dictionary::ENGLISH, 'house', 5), $tail]));
    }

    public function testTheFirstOfPatternsOfTheSameLength(): void
    {
        $sequence = [
            new PatternMatch(Pattern::Sequence, 0, 2, 'abc', 12.0),
            new PatternMatch(Pattern::Repeat, 3, 5, 'zzz', 12.0, baseToken: 'z', repeatCount: 3),
        ];

        self::assertSame(Warning::Sequence, FeedbackBuilder::build(Score::Weak, $sequence)->warning);
    }

    /**
     * @param array<array-key, string> $substitutions
     */
    private static function word(string $dictionary, string $token, int $rank, bool $reversed = false, array $substitutions = [], int $start = 0): PatternMatch
    {
        return new PatternMatch(
            Pattern::Dictionary,
            $start,
            $start + mb_strlen($token) - 1,
            $token,
            1.0,
            $dictionary,
            $rank,
            mb_strtolower($token),
            $reversed,
            $substitutions,
        );
    }
}
