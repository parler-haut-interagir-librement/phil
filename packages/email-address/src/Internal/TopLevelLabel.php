<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Internal;

/**
 * A top-level label as the root zone delegates it: letters only, or an A-label (`xn--`) whose Unicode form IDNA
 * accepts; two to 63 octets, in lowercase.
 *
 * @internal
 */
final class TopLevelLabel
{
    /**
     * @return string|null the label in lowercase ASCII, or null when it cannot be a top-level domain
     */
    public static function normalize(string $label): ?string
    {
        $lower = strtolower($label);

        if (1 === preg_match('/^[a-z]{2,63}$/', $lower)) {
            return $lower;
        }

        if (1 !== preg_match('/^xn--[a-z0-9](?:[a-z0-9-]{0,57}[a-z0-9])?$/', $lower)) {
            return null;
        }

        // ext-intl answers false for a punycode that does not decode or a label IDNA refuses.
        $unicode = idn_to_utf8($lower, \IDNA_NONTRANSITIONAL_TO_UNICODE | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ | \IDNA_USE_STD3_RULES, \INTL_IDNA_VARIANT_UTS46);

        return false === $unicode ? null : $lower;
    }
}
