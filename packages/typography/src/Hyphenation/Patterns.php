<?php

declare(strict_types=1);

namespace Phil\Typography\Hyphenation;

/**
 * Liang's hyphenation patterns of one language, read from the TeX format: each pattern is a string of letters with
 * digits between them (`.ach4`, `a1b`), an odd digit allowing a break and an even one forbidding it; exceptions are
 * whole words with their breaks marked by hyphens (`ta-ble`). `$left` and `$right` are the fewest letters kept before
 * the first break and after the last.
 */
final readonly class Patterns
{
    /**
     * @param array<string, array<int, int>> $patterns   each string of letters, to its digits by position
     * @param int                            $longest    the length of the longest pattern, in letters
     * @param array<string, list<int>>       $exceptions each word in lower case, to its breaks
     */
    private function __construct(
        public array $patterns,
        public int $longest,
        public array $exceptions,
        public int $left,
        public int $right,
    ) {
    }

    /**
     * @param string $patterns   the patterns, separated by white space
     * @param string $exceptions the exceptions, separated by white space
     */
    public static function parse(string $patterns, string $exceptions = '', int $left = 2, int $right = 2): self
    {
        $parsed = [];
        $longest = 0;

        foreach (self::words($patterns) as $pattern) {
            $letters = '';
            $digits = [];
            $count = 0;

            foreach (mb_str_split($pattern) as $character) {
                if (is_numeric($character)) {
                    $digits[$count] = (int) $character;
                } else {
                    $letters .= $character;
                    ++$count;
                }
            }

            $parsed[$letters] = $digits;
            $longest = max($longest, $count);
        }

        $breaks = [];

        foreach (self::words($exceptions) as $exception) {
            $positions = [];
            $count = 0;

            foreach (mb_str_split($exception) as $character) {
                if ('-' === $character) {
                    $positions[] = $count;
                } else {
                    ++$count;
                }
            }

            $breaks[mb_strtolower(str_replace('-', '', $exception))] = $positions;
        }

        return new self($parsed, $longest, $breaks, max(1, $left), max(1, $right));
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/\s+/', $text, -1, \PREG_SPLIT_NO_EMPTY);

        return false === $words ? [] : $words;
    }
}
