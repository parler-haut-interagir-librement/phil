<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Internal;

use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\SyntaxError;

/**
 * A host name checked by the rules of IDNA (UTS #46, nontransitional, STD3) and of RFC 5321: its ASCII form, every
 * label at most 63 octets, the whole at most 253, at least two labels, a top-level label neither numeric nor a single
 * letter.
 *
 * @internal
 */
final readonly class DomainName
{
    private const int OPTIONS = \IDNA_NONTRANSITIONAL_TO_ASCII | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ | \IDNA_USE_STD3_RULES;

    private function __construct(
        public string $ascii,
        public string $unicode,
    ) {
    }

    /**
     * @throws InvalidEmailAddress
     */
    public static function of(string $domain): self
    {
        if ('' === $domain) {
            throw new InvalidEmailAddress(SyntaxError::EmptyDomain);
        }

        // UTS #46 lets an empty label through as the root; an address names no root.
        if (str_starts_with($domain, '.') || str_ends_with($domain, '.') || str_contains($domain, '..')) {
            throw new InvalidEmailAddress(SyntaxError::InvalidDomain);
        }

        $info = [];
        $ascii = idn_to_ascii($domain, self::OPTIONS, \INTL_IDNA_VARIANT_UTS46, $info);

        if (false === $ascii) {
            $errors = self::errors($info);

            throw new InvalidEmailAddress(match (true) {
                0 !== ($errors & \IDNA_ERROR_LABEL_TOO_LONG) => SyntaxError::LabelTooLong, 0 !== ($errors & \IDNA_ERROR_DOMAIN_NAME_TOO_LONG) => SyntaxError::DomainTooLong, default => SyntaxError::InvalidDomain,
            });
        }

        $dot = strrpos($ascii, '.');

        if (false === $dot) {
            throw new InvalidEmailAddress(SyntaxError::SingleLabelDomain);
        }

        $tld = substr($ascii, $dot + 1);

        if (1 === \strlen($tld) || ctype_digit($tld)) {
            throw new InvalidEmailAddress(SyntaxError::InvalidTopLevelDomain);
        }

        $unicode = idn_to_utf8($ascii, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);

        return new self($ascii, false === $unicode ? $ascii : $unicode);
    }

    /**
     * The IDNA errors ext-intl reports. It reports none when the ASCII form is longer than a domain name can be.
     */
    private static function errors(mixed $info): int
    {
        return \is_array($info) && \is_int($info['errors'] ?? null) ? $info['errors'] : \IDNA_ERROR_DOMAIN_NAME_TOO_LONG;
    }
}
