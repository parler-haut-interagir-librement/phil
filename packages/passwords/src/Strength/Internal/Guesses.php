<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

/**
 * The counting the guess estimates share: binomial coefficients and the variations of case and of substitutions an
 * attacker tries on a word.
 *
 * @internal
 */
final class Guesses
{
    /** The guesses of a character that follows no pattern. */
    public const float BRUTEFORCE_CARDINALITY = 10.0;

    /** The least a part of a longer password costs, so that cutting it in many small patterns is never cheaper. */
    public const float MIN_SINGLE_CHARACTER = 10.0;

    public const float MIN_SUBMATCH = 50.0;

    /** The span of years an attacker tries around the reference year, at the least. */
    public const int MIN_YEAR_SPACE = 20;

    public static function binomial(int $n, int $k): float
    {
        $result = 1.0;

        for ($i = 1; $i <= $k; ++$i) {
            $result = $result * ($n - $i + 1) / $i;
        }

        return $result;
    }

    /**
     * The ways of choosing which of `$changed + $unchanged` characters change, at least one and at most the smaller
     * count, for at least one changed character: 2 when all of them change, as an attacker tries both forms first.
     */
    public static function variations(int $changed, int $unchanged): float
    {
        if (0 === $unchanged) {
            return 2.0;
        }

        $sum = 0.0;

        for ($i = 1; $i <= min($changed, $unchanged); ++$i) {
            $sum += self::binomial($changed + $unchanged, $i);
        }

        return $sum;
    }

    /**
     * The variations of case of a word: 1 in lowercase or without letters; 2 capitalised, with only its last letter
     * in uppercase, or all in uppercase, as `variations()` counts no lowercase letter; otherwise by the count of
     * uppercase and lowercase letters.
     */
    public static function uppercaseVariations(string $token): float
    {
        $upper = preg_match_all('/\p{Lu}/u', $token);
        $lower = preg_match_all('/\p{Ll}/u', $token);

        if (0 === $upper) {
            return 1.0;
        }

        if (1 === preg_match('/^\p{Lu}[^\p{Lu}]+$/u', $token) || 1 === preg_match('/^[^\p{Lu}]+\p{Lu}$/u', $token)) {
            return 2.0;
        }

        return self::variations((int) $upper, (int) $lower);
    }

    /**
     * The variations of substitutions of a word: for each character that stood for a letter, by its count and the
     * count of that letter left as it is.
     *
     * @param array<array-key, string> $substitutions
     */
    public static function l33tVariations(string $token, array $substitutions): float
    {
        $lower = mb_strtolower($token);
        $variations = 1.0;

        foreach ($substitutions as $symbol => $letter) {
            $variations *= self::variations(substr_count($lower, (string) $symbol), substr_count($lower, $letter));
        }

        return $variations;
    }
}
