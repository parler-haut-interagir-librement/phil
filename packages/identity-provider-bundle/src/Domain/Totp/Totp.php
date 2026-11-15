<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Totp;

use Phil\IdentityProviderBundle\Domain\Encoding\Base32;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226): HMAC-SHA-1 of the number of periods since the epoch,
 * truncated to six digits, as every authenticator application computes them. A code is accepted within `window`
 * periods before and after the current one, and only once: `verify()` returns the counter it matched, which the
 * caller keeps and passes back, so that the same code — or an older one — never verifies twice.
 */
final readonly class Totp
{
    public const int DIGITS = 6;

    public const int PERIOD = 30;

    /** The fewest bytes of a secret: RFC 4226 asks for 128 bits, and 160 is what applications expect. */
    public const int MIN_SECRET_BYTES = 16;

    public const int SECRET_BYTES = 20;

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private int $window = 1,
    ) {
        if (\strlen($secret) < self::MIN_SECRET_BYTES) {
            throw InvalidValue::because(\sprintf('A TOTP secret holds at least %d bytes.', self::MIN_SECRET_BYTES));
        }

        if ($window < 0 || $window > 10) {
            throw InvalidValue::because('A TOTP window is 0 to 10 periods.');
        }
    }

    public static function generateSecret(): string
    {
        return random_bytes(self::SECRET_BYTES);
    }

    public static function fromBase32(#[SensitiveParameter] string $secret, int $window = 1): self
    {
        return new self(Base32::decode($secret), $window);
    }

    public function counter(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    /**
     * The code of the period that holds the timestamp.
     */
    public function at(int $timestamp): string
    {
        return $this->code($this->counter($timestamp));
    }

    /**
     * The counter the code matched, within the window around the timestamp and after the last counter accepted;
     * null when it matches none. Every counter of the window is computed, so that the time taken says nothing of which
     * one matched.
     */
    public function verify(#[SensitiveParameter] string $code, int $timestamp, ?int $lastCounter = null): ?int
    {
        $code = str_replace(' ', '', $code);

        $current = $this->counter($timestamp);
        $matched = null;

        for ($counter = $current - $this->window; $counter <= $current + $this->window; ++$counter) {
            if (hash_equals($this->code($counter), $code) && (null === $lastCounter || $counter > $lastCounter)) {
                $matched ??= $counter;
            }
        }

        return $matched;
    }

    /**
     * The `otpauth://` address authenticator applications read from a QR code (the Key Uri Format): the label is the
     * issuer and the account name, the parameters the secret and the issuer again.
     */
    public function provisioningUri(string $issuer, string $accountName): string
    {
        return \sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            http_build_query(['secret' => Base32::encode($this->secret), 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD], '', '&', \PHP_QUERY_RFC3986),
        );
    }

    public function base32Secret(): string
    {
        return Base32::encode($this->secret);
    }

    private function code(int $counter): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), $this->secret, true);
        $offset = \ord($hash[19]) & 0x0F;
        $value = ((\ord($hash[$offset]) & 0x7F) << 24)
            | (\ord($hash[$offset + 1]) << 16)
            | (\ord($hash[$offset + 2]) << 8)
            | \ord($hash[$offset + 3]);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', \STR_PAD_LEFT);
    }
}
