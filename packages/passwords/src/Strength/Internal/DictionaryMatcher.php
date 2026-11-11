<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\Dictionary;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * Finds the entries of the dictionaries in a password: as written, reversed, and with characters that stand for
 * letters, as `p@ssw0rd`.
 *
 * The dictionaries are merged in one index of their entries and of the prefixes of their entries, built once: from
 * each position, the part of the password grows one character at a time and stops at the first that is no prefix,
 * so that the work grows with the length of the password times the length of the longest entry, in practice far
 * less. The substitutions tried are bounded: only `1`, `|` and `7` may stand for two letters each, so that a password
 * is read with at most eight tables of substitutions.
 *
 * @internal
 */
final readonly class DictionaryMatcher
{
    /** The letters a character may stand for. */
    private const array L33T = [
        '4' => ['a'], '@' => ['a'], '8' => ['b'], '(' => ['c'], '{' => ['c'], '[' => ['c'], '<' => ['c'],
        '3' => ['e'], '6' => ['g'], '9' => ['g'], '1' => ['i', 'l'], '!' => ['i'], '|' => ['i', 'l'],
        '0' => ['o'], '$' => ['s'], '5' => ['s'], '+' => ['t'], '7' => ['t', 'l'], '%' => ['x'], '2' => ['z'],
    ];

    /**
     * @param array<array-key, list<array{string, int}>> $entries  each entry to the dictionaries that hold it, with its rank
     * @param array<array-key, string>                   $prefixes every prefix of an entry, the entries included, to itself
     */
    private function __construct(
        private array $entries,
        private array $prefixes,
    ) {
    }

    /**
     * @param list<Dictionary> $dictionaries
     */
    public static function of(array $dictionaries): self
    {
        return new self([], [])->with(...$dictionaries);
    }

    public function with(Dictionary ...$dictionaries): self
    {
        $entries = $this->entries;
        $prefixes = $this->prefixes;

        foreach ($dictionaries as $dictionary) {
            foreach ($dictionary->ranks() as $entry => $rank) {
                $entry = (string) $entry;
                $entries[$entry][] = [$dictionary->name, $rank];

                $prefix = '';

                // Every prefix of bytes, so that every prefix of characters is there too.
                foreach (str_split($entry) as $byte) {
                    $prefix .= $byte;
                    $prefixes[$prefix] = $prefix;
                }
            }
        }

        return new self($entries, $prefixes);
    }

    /**
     * @param list<string> $characters
     *
     * @return list<PatternMatch>
     */
    public function matches(array $characters): array
    {
        $lower = array_map(mb_strtolower(...), $characters);
        $matches = $this->find($characters, $lower, []);
        $count = \count($characters);

        foreach ($this->find(array_reverse($characters), array_reverse($lower), []) as $match) {
            $token = implode('', array_reverse(mb_str_split($match->token)));

            if (mb_strtolower($token) === $match->word) {
                continue;
            }

            $matches[] = new PatternMatch(
                Pattern::Dictionary,
                $count - 1 - $match->end,
                $count - 1 - $match->start,
                $token,
                $match->guesses * 2,
                $match->dictionary,
                $match->rank,
                $match->word,
                reversed: true,
            );
        }

        // A table that differs from another only by characters a part does not hold finds that part again: each
        // substituted match is kept once, by its span, its dictionary and its word.
        $substituted = [];

        foreach ($this->substitutionTables($lower) as $table) {
            $translated = array_map(static fn (string $character): string => $table[$character] ?? $character, $lower);

            foreach ($this->find($characters, $translated, $table) as $match) {
                $substituted[$match->start][$match->end][$match->dictionary ?? ''][$match->word ?? ''] = $match;
            }
        }

        foreach ($substituted as $ends) {
            foreach ($ends as $dictionaries) {
                foreach ($dictionaries as $words) {
                    array_push($matches, ...array_values($words));
                }
            }
        }

        return $matches;
    }

    /**
     * The entries in the password; with a table of substitutions, only those that hold one, of two characters or more.
     *
     * @param list<string>             $characters
     * @param list<string>             $lower
     * @param array<array-key, string> $table
     *
     * @return list<PatternMatch>
     */
    private function find(array $characters, array $lower, array $table): array
    {
        $matches = [];
        $count = \count($lower);

        foreach (array_keys($lower) as $i) {
            $word = '';

            for ($j = $i; $j < $count && isset($this->prefixes[$word .= $lower[$j]]); ++$j) {
                if (!isset($this->entries[$word])) {
                    continue;
                }

                $part = \array_slice($characters, $i, $j - $i + 1);
                $token = implode('', $part);
                $substitutions = $this->substitutionsIn($part, $table);

                if ([] !== $table && ([] === $substitutions || $j === $i)) {
                    continue;
                }

                foreach ($this->entries[$word] as [$dictionary, $rank]) {
                    $matches[] = new PatternMatch(
                        Pattern::Dictionary,
                        $i,
                        $j,
                        $token,
                        $rank * Guesses::uppercaseVariations($token) * Guesses::l33tVariations($token, $substitutions),
                        $dictionary,
                        $rank,
                        $word,
                        substitutions: $substitutions,
                    );
                }
            }
        }

        return $matches;
    }

    /**
     * Every table of substitutions for the characters of the password that may stand for letters, a character that may
     * stand for several letters giving one table for each.
     *
     * @param list<string> $lower
     *
     * @return list<array<array-key, string>>
     */
    private function substitutionTables(array $lower): array
    {
        /** @var list<array<array-key, string>> $tables */
        $tables = [[]];

        $present = array_flip($lower);

        foreach (self::L33T as $character => $letters) {
            if (!isset($present[$character])) {
                continue;
            }

            $next = [];

            foreach ($tables as $table) {
                foreach ($letters as $letter) {
                    $next[] = $table + [$character => $letter];
                }
            }

            $tables = $next;
        }

        return [[]] === $tables ? [] : $tables;
    }

    /**
     * @param list<string>             $part
     * @param array<array-key, string> $table
     *
     * @return array<array-key, string>
     */
    private function substitutionsIn(array $part, array $table): array
    {
        $substitutions = [];

        foreach ($part as $character) {
            if (isset($table[$character])) {
                $substitutions[$character] = $table[$character];
            }
        }

        return $substitutions;
    }
}
