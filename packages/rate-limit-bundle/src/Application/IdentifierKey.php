<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Symfony\Component\String\UnicodeString;

/**
 * The form of an identifier a person types or is known by — a username, an e-mail address — that a key is built
 * from, as Symfony's login throttling builds its own: compatibility decomposition, case folding, then the combining
 * marks, the format characters (zero-width spaces, joiners, direction marks) and the control characters removed, and
 * the white space around trimmed. `Ada`, `ADA`, `Àda` and `Ada` with a zero-width space count as one.
 *
 * The decomposition and the folding go through `symfony/string`, without the full case folding of the intl extension
 * (`Normalizer::NFKC_CF`), which its polyfill lacks; a final sigma folds to a sigma, which the mbstring extension
 * writes and its polyfill does not. The key is therefore the same with the intl and mbstring extensions or with the
 * polyfills of `symfony/string`, for every character Unicode 13.0 assigns; a character assigned later is read as
 * the Unicode data of each implementation knows it.
 *
 * An identifier that is not valid UTF-8 is only trimmed and lower-cased, byte by byte.
 */
final class IdentifierKey
{
    public static function normalize(string $identifier): string
    {
        if (1 !== preg_match('//u', $identifier)) {
            return strtolower(trim($identifier));
        }

        $folded = new UnicodeString($identifier)
            ->normalize(UnicodeString::NFKD)
            ->folded(false)
            ->normalize(UnicodeString::NFKD)
            ->toString();

        return trim((string) preg_replace('/[\p{Mn}\p{Me}\p{Cf}\p{Cc}]++/u', '', str_replace("\u{03C2}", "\u{03C3}", $folded)));
    }
}
