<?php

declare(strict_types=1);

namespace Phil\Typography\Hyphenation;

use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Locale;
use Phil\Typography\Text\Chars;

/**
 * Liang's algorithm, the one TeX hyphenates with: every pattern found in a word, framed by dots, raises the digits
 * between its letters, and a break is allowed where the highest digit is odd, never closer to either end than the
 * language allows, and never before a combining mark. A word listed among the exceptions is broken where the exception
 * says.
 */
final readonly class Hyphenator
{
    private int $left;

    private int $right;

    /**
     * @param int|null $left  the fewest letters before the first break, the language's own when null
     * @param int|null $right the fewest letters after the last break, the language's own when null
     */
    public function __construct(
        private Patterns $patterns,
        ?int $left = null,
        ?int $right = null,
    ) {
        $this->left = max(1, $left ?? $patterns->left);
        $this->right = max(1, $right ?? $patterns->right);
    }

    /**
     * The hyphenator of a locale (`fr_CA`, `de_CH`) or of a pattern tag (`de-1901`, `la-x-classic`).
     *
     * @throws InvalidLocale
     * @throws UnsupportedLanguage
     */
    public static function for(Locale|string $language, ?Dictionary $dictionary = null): self
    {
        return new self(($dictionary ?? new Dictionary())->patterns($language));
    }

    /**
     * Where the word may break: the number of characters before each break.
     *
     * @return list<int>
     */
    public function breaks(string $word): array
    {
        $lower = mb_strtolower($word);
        $letters = mb_str_split($lower);
        $length = \count($letters);

        if ($length !== mb_strlen($word)) {
            return [];
        }

        $points = $this->points($lower, $letters);
        $breaks = [];

        for ($position = $this->left; $position <= $length - $this->right; ++$position) {
            if (isset($points[$position + 1]) && 1 === $points[$position + 1] % 2 && 1 !== preg_match('/\p{M}/u', $letters[$position])) {
                $breaks[] = $position;
            }
        }

        return $breaks;
    }

    /**
     * The word with `$hyphen` — a soft hyphen by default — at each break.
     */
    public function hyphenate(string $word, string $hyphen = Chars::SOFT_HYPHEN): string
    {
        $breaks = array_flip($this->breaks($word));
        $hyphenated = '';

        foreach (mb_str_split($word) as $position => $character) {
            $hyphenated .= isset($breaks[$position]) ? $hyphen.$character : $character;
        }

        return $hyphenated;
    }

    /**
     * The digits between the characters of the word framed by dots, where a pattern or an exception set one: the one
     * before character `n` of the word is at index `n + 1`.
     *
     * @param list<string> $letters
     *
     * @return array<int, int>
     */
    private function points(string $lower, array $letters): array
    {
        $points = [];

        if (isset($this->patterns->exceptions[$lower])) {
            foreach ($this->patterns->exceptions[$lower] as $position) {
                $points[$position + 1] = 1;
            }

            return $points;
        }

        $framed = ['.', ...$letters, '.'];
        $size = \count($framed);

        foreach (array_keys($framed) as $start) {
            $key = '';

            for ($end = $start; $end < min($size, $start + $this->patterns->longest); ++$end) {
                $key .= $framed[$end];

                foreach ($this->patterns->patterns[$key] ?? [] as $offset => $digit) {
                    $points[$start + $offset] = max($points[$start + $offset] ?? $digit, $digit);
                }
            }
        }

        return $points;
    }
}
