<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Generator;

/**
 * Writes the Liang patterns of a language from its syllabification rules. Every rule becomes patterns over the letters
 * it names, at two levels: `1` allows a break, `2` forbids one, so that an inseparable cluster, a digraph or a prefix
 * always wins over the general rule. The output is sorted, one pattern per line, so that it is stable and its diff
 * readable.
 *
 * - Before the last consonant: `1` before each consonant and each onset followed by a vowel; `2` after the first two
 *   letters of a word that begins with two consonants, unless the second carries a syllable.
 * - After the first consonant: `1` before a consonant between vowels, after the first consonant of a group that
 *   follows a vowel, and before an onset that follows a vowel; `2` before the last two letters when both are
 *   consonants, so that no break leaves a part without a vowel.
 * - Always: `2` inside each digraph and each onset, `1` inside each hiatus, `2` before a consonant followed by a silent
 *   ending, and the hand-written patterns as they are.
 */
final class PatternGenerator
{
    public static function generate(SyllableRules $rules): string
    {
        $patterns = [];
        $symbols = [...$rules->consonants, ...$rules->units];

        foreach ([...$rules->units, ...$rules->onsets] as $cluster) {
            $patterns[] = implode('2', mb_str_split($cluster));
        }

        if (Split::BeforeLast === $rules->split) {
            foreach ([...$symbols, ...$rules->onsets] as $consonant) {
                foreach ($rules->vowels as $vowel) {
                    $patterns[] = '1'.$consonant.$vowel;
                }
            }

            foreach ($rules->consonants as $first) {
                foreach (array_diff($rules->consonants, $rules->syllabic) as $second) {
                    $patterns[] = '.'.$first.$second.'2';
                }
            }
        } else {
            foreach ($rules->vowels as $vowel) {
                foreach ($symbols as $consonant) {
                    foreach ($rules->vowels as $next) {
                        $patterns[] = $vowel.'1'.$consonant.$next;
                    }

                    foreach ($symbols as $second) {
                        $patterns[] = $vowel.$consonant.'1'.$second;
                    }
                }

                foreach ($rules->onsets as $onset) {
                    $patterns[] = $vowel.'1'.$onset;
                }
            }

            foreach ($rules->consonants as $first) {
                foreach ($rules->consonants as $second) {
                    $patterns[] = '2'.$first.$second.'.';
                }
            }
        }

        foreach ($rules->hiatus as $pair) {
            $patterns[] = implode('1', mb_str_split($pair));
        }

        foreach ([...$symbols, ...$rules->onsets] as $consonant) {
            foreach ($rules->silentEndings as $ending) {
                $patterns[] = '2'.$consonant.$ending.'.';
            }
        }

        return self::write(self::merge([...$patterns, ...$rules->patterns]));
    }

    /**
     * The patterns that share their letters merged into one, keeping the highest digit at each position.
     *
     * @param list<string> $patterns
     *
     * @return array<string, array<int, int>>
     */
    private static function merge(array $patterns): array
    {
        $merged = [];

        foreach ($patterns as $pattern) {
            $letters = '';
            $count = 0;
            $digits = [];

            foreach (mb_str_split($pattern) as $character) {
                if (ctype_digit($character)) {
                    $digits[$count] = (int) $character;
                } else {
                    $letters .= $character;
                    ++$count;
                }
            }

            foreach ($digits as $position => $digit) {
                $merged[$letters][$position] = max($merged[$letters][$position] ?? 0, $digit);
            }
        }

        ksort($merged, \SORT_STRING);

        return $merged;
    }

    /**
     * @param array<string, array<int, int>> $patterns
     */
    private static function write(array $patterns): string
    {
        $lines = [];

        foreach ($patterns as $letters => $digits) {
            $line = '';

            foreach (mb_str_split($letters) as $position => $letter) {
                $line .= ($digits[$position] ?? '').$letter;
            }

            $lines[] = $line.($digits[mb_strlen($letters)] ?? '');
        }

        return implode("\n", $lines)."\n";
    }
}
