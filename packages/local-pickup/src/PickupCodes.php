<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;
use SensitiveParameter;

/**
 * Issues the short code a customer gives at the counter, and checks it.
 *
 * A code is eight characters of Crockford's base 32 — digits and capitals without I, L, O nor U —, forty bits drawn
 * by the system's secure random generator, shown in two groups of four. Only its HMAC-SHA-256 digest, bound to the
 * order and keyed by a key derived from the secret with HKDF under the label `phil_local_pickup.code_digest`, is kept: a copy of the database alone neither reveals a code nor lets
 * one be tried offline. Reading a code is forgiving: case, spaces and dashes are ignored, O reads as 0, I and L as 1.
 *
 * The service keeps no state between calls.
 */
final readonly class PickupCodes
{
    public const int LENGTH = 8;

    /** The shortest secret accepted, in bytes. */
    public const int MIN_SECRET = 32;

    /** The HKDF label of the key of the digests, so that no other use of the secret yields the same digests. */
    public const string KEY_LABEL = 'phil_local_pickup.code_digest';

    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private string $key;

    public function __construct(#[SensitiveParameter] string $secret)
    {
        if (\strlen($secret) < self::MIN_SECRET) {
            throw InvalidPickup::because('pickup code secret', \sprintf('is at least %d bytes', self::MIN_SECRET));
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::KEY_LABEL);
    }

    /**
     * A new code for the order, with its digest.
     */
    public function issue(string $order): IssuedCode
    {
        Check::order($order);
        $code = '';

        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return new IssuedCode($order, $code, $this->digest($order, $code));
    }

    /**
     * Whether a code given for the order is the one whose digest is kept, compared in constant time.
     */
    public function matches(string $order, #[SensitiveParameter] string $given, string $digest): bool
    {
        $code = self::normalize($given);

        return null !== $code && hash_equals($digest, $this->digest(Check::order($order), $code));
    }

    /**
     * The code as typed, read into its eight characters; null when it cannot be one.
     */
    public static function normalize(#[SensitiveParameter] string $given): ?string
    {
        if (\strlen($given) > 32) {
            return null;
        }

        $code = strtr(strtoupper(str_replace([' ', '-'], '', $given)), 'OIL', '011');

        return self::LENGTH === \strlen($code) && \strlen($code) === strspn($code, self::ALPHABET) ? $code : null;
    }

    private function digest(string $order, string $code): string
    {
        return hash_hmac('sha256', $order."\n".$code, $this->key);
    }
}
