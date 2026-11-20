<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Normalizer;

/**
 * The form of an identifier a person types or is known by — a username, an e-mail address — that a key is built
 * from, as Symfony's login throttling builds its own: compatibility decomposition, case folding, then the combining
 * marks, the format characters (zero-width spaces, joiners, direction marks) and the control characters removed, and
 * the white space around trimmed. `Ada`, `ADA`, `Àda` and `Ada` with a zero-width space count as one.
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

        $decomposed = Normalizer::normalize($identifier, Normalizer::FORM_KD);
        $folded = mb_convert_case(\is_string($decomposed) ? $decomposed : $identifier, \MB_CASE_FOLD, 'UTF-8');

        return trim((string) preg_replace('/[\p{Mn}\p{Me}\p{Cf}\p{Cc}]++/u', '', $folded));
    }
}
