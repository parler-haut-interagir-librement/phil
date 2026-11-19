<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;

/**
 * The initials of a name, by grapheme clusters rather than bytes or code points: a letter and its accents, a Hangul
 * syllable, a flag stay whole.
 *
 * The name is cut into words at spaces and punctuation; each word gives its first letter or digit, uppercased one for
 * one, keeping its combining marks. The first words and the last one give the initials — "Ada Lovelace" gives "AL",
 * "Jean-Luc de la Tour" gives "JT", or "JLT" for three letters —, and the first word alone gives one letter. A name in Han, Hiragana, Katakana or Hangul gives its first character alone, these
 * scripts writing a name without spaces and a character filling the space of two letters. Right-to-left names, in
 * Arabic or Hebrew, keep their logical order; `isRightToLeft()` tells the drawing to lay them out that way.
 *
 * A name that is not valid UTF-8, or has no letter or digit, gives no initials. A name longer than 1 KiB is read from
 * its first kilobyte only.
 */
final readonly class Initials
{
    public const int MAX_NAME_BYTES = 1024;

    public const int MAX_LETTERS = 3;

    private const string WIDE_SCRIPTS = '/^[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';

    private const string RIGHT_TO_LEFT = '/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}\p{Nko}\p{Samaritan}\p{Mandaic}\p{Adlam}]/u';

    private function __construct(
        public string $letters,
        private bool $rightToLeft,
    ) {
    }

    /**
     * @param int $max the most letters to keep, from 1 to 3
     *
     * @throws InvalidAvatar when the most letters is out of bounds
     */
    public static function of(string $name, int $max = 2): self
    {
        if ($max < 1 || $max > self::MAX_LETTERS) {
            throw InvalidAvatar::because(\sprintf('Initials hold 1 to %d letters.', self::MAX_LETTERS));
        }

        if (\strlen($name) > self::MAX_NAME_BYTES) {
            $name = mb_strcut($name, 0, self::MAX_NAME_BYTES, 'UTF-8');
        }

        if (!mb_check_encoding($name, 'UTF-8')) {
            return new self('', false);
        }

        $initials = [];

        $words = preg_split('/[\p{Z}\s\p{P}\p{S}\p{Cc}]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY);
        \assert(\is_array($words));

        foreach ($words as $word) {
            $initial = self::initialOf($word);

            if (null !== $initial) {
                $initials[] = $initial;
            }
        }

        if ([] === $initials) {
            return new self('', false);
        }

        if (1 === $max || 1 === preg_match(self::WIDE_SCRIPTS, $initials[0])) {
            $initials = [$initials[0]];
        }

        $last = array_pop($initials);
        $letters = implode('', [...\array_slice($initials, 0, $max - 1), $last]);

        return new self($letters, 1 === preg_match(self::RIGHT_TO_LEFT, $letters));
    }

    public function isEmpty(): bool
    {
        return '' === $this->letters;
    }

    /**
     * Whether the initials are in a right-to-left script.
     */
    public function isRightToLeft(): bool
    {
        return $this->rightToLeft;
    }

    /**
     * The first grapheme cluster of a word that starts with a letter or a digit, its base uppercased one for one.
     */
    private static function initialOf(string $word): ?string
    {
        preg_match_all('/\X/u', $word, $clusters);

        foreach ($clusters[0] as $cluster) {
            if (1 !== preg_match('/^([\p{L}\p{N}])(.*)$/us', $cluster, $parts)) {
                continue;
            }

            return mb_convert_case($parts[1], \MB_CASE_UPPER_SIMPLE, 'UTF-8').$parts[2];
        }

        return null;
    }
}
