<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * The code a certificate is found by in the register: twelve characters of Crockford's Base32 — sixty random bits, too
 * many to guess —, printed in groups of four. Read back, it forgives lower case, dashes and spaces, and the letters
 * read as digits: I and L as 1, O as 0.
 */
final readonly class VerificationCode
{
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const string PATTERN = '[0-9A-HJKMNP-TV-Z]{12}';

    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        $code = '';

        foreach (str_split(random_bytes(12)) as $byte) {
            $code .= self::ALPHABET[\ord($byte[0]) & 31];
        }

        return new self($code);
    }

    public static function fromString(string $code): self
    {
        $normalized = strtr(strtoupper(str_replace(['-', ' '], '', $code)), ['I' => '1', 'L' => '1', 'O' => '0']);

        if (1 !== preg_match('/^'.self::PATTERN.'$/D', $normalized)) {
            throw InvalidValue::because(\sprintf('"%s" is not a verification code: twelve letters and digits.', $code));
        }

        return new self($normalized);
    }

    /**
     * The code in groups of four, as it is printed: `7KQ2-M9XA-04TZ`.
     */
    public function formatted(): string
    {
        return implode('-', str_split($this->value, 4));
    }
}
