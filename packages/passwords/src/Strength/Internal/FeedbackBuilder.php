<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\Dictionary;
use Phil\Passwords\Strength\Feedback;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\Suggestion;
use Phil\Passwords\Strength\Warning;

/**
 * The feedback of a password from its score and the longest of its patterns.
 *
 * @internal
 */
final class FeedbackBuilder
{
    /**
     * @param list<PatternMatch> $sequence
     */
    public static function build(Score $score, array $sequence): Feedback
    {
        if ([] === $sequence) {
            return new Feedback(null, [Suggestion::UseWords, Suggestion::NoSymbolsNeeded]);
        }

        if ($score->isAtLeast(Score::Strong)) {
            return new Feedback();
        }

        $longest = $sequence[0];

        foreach ($sequence as $match) {
            if ($match->length() > $longest->length()) {
                $longest = $match;
            }
        }

        [$warning, $suggestions] = self::forPattern($longest, 1 === \count($sequence));

        return new Feedback($warning, [Suggestion::AddWord, ...$suggestions]);
    }

    /**
     * @return array{?Warning, list<Suggestion>}
     */
    private static function forPattern(PatternMatch $match, bool $alone): array
    {
        return match ($match->pattern) {
            Pattern::Dictionary => self::forWord($match, $alone),
            Pattern::Spatial => [1 === $match->turns ? Warning::StraightRow : Warning::ShortKeyboardPattern, [Suggestion::LongerKeyboardPattern]],
            Pattern::Repeat => [1 === mb_strlen((string) $match->baseToken) ? Warning::RepeatedCharacter : Warning::RepeatedPattern, [Suggestion::AvoidRepeats]],
            Pattern::Sequence => [Warning::Sequence, [Suggestion::AvoidSequences]],
            Pattern::Year => [Warning::RecentYear, [Suggestion::AvoidRecentYears]],
            Pattern::Date => [Warning::Date, [Suggestion::AvoidDates]],
            Pattern::Bruteforce => [null, []],
        };
    }

    /**
     * @return array{?Warning, list<Suggestion>}
     */
    private static function forWord(PatternMatch $match, bool $alone): array
    {
        $warning = match ($match->dictionary) {
            Dictionary::PASSWORDS => match (true) {
                !$alone || $match->isL33t() || $match->reversed => Warning::SimilarToCommon,
                $match->rank <= 10 => Warning::TopTen,
                $match->rank <= 100 => Warning::TopHundred,
                default => Warning::CommonPassword,
            },
            Dictionary::NAMES, Dictionary::SURNAMES => $alone ? Warning::Name : Warning::CommonName,
            Dictionary::USER_INPUTS => Warning::UserInput,
            default => $alone ? Warning::SingleWord : null,
        };

        $suggestions = [];
        $token = $match->token;

        if (1 === preg_match('/^\p{Lu}[^\p{Lu}]+$/u', $token)) {
            $suggestions[] = Suggestion::Capitalization;
        } elseif (1 === preg_match('/^[^\p{Ll}]+$/u', $token) && mb_strtolower($token) !== $token) {
            $suggestions[] = Suggestion::AllUppercase;
        }

        if ($match->reversed && mb_strlen($token) >= 4) {
            $suggestions[] = Suggestion::ReversedWords;
        }

        if ($match->isL33t()) {
            $suggestions[] = Suggestion::Substitutions;
        }

        if (Dictionary::USER_INPUTS === $match->dictionary) {
            $suggestions[] = Suggestion::AvoidPersonalInformation;
        }

        return [$warning, $suggestions];
    }
}
