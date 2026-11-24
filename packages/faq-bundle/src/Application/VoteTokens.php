<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use DateTimeImmutable;
use Phil\FaqBundle\Application\Exception\VoteRefused;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The vote tokens of a page: 16 random bytes and an expiry, signed with HMAC-SHA-256 under the application's secret.
 * A token needs no session and no cookie, and carries nothing about the reader; it votes once for each question of the
 * page, which the spent tokens remember until it expires.
 *
 * A token is `<random part>.<expiry>.<signature>`, in base64url without padding and decimal seconds.
 */
final readonly class VoteTokens
{
    /** The shortest lifetime of a token, in seconds. */
    public const int MIN_LIFETIME = 60;

    /** The longest lifetime of a token, in seconds: a week. */
    public const int MAX_LIFETIME = 604_800;

    /** The longest token read, the longest the pattern reads: a nonce of 22 characters, 12 digits and a mac of 43. */
    public const int MAX_LENGTH = 79;

    private const string CONTEXT = 'phil_faq_vote';

    private const string PATTERN = '/^(?<nonce>[A-Za-z0-9_-]{22})\.(?<expiry>[1-9]\d{0,11})\.(?<mac>[A-Za-z0-9_-]{43})$/D';

    /**
     * @param int<self::MIN_LIFETIME, self::MAX_LIFETIME> $lifetime how long a token votes, in seconds
     */
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
        private int $lifetime = 86_400,
    ) {
    }

    public function issue(): string
    {
        $payload = $this->encode(random_bytes(16)).'.'.($this->clock->now()->getTimestamp() + $this->lifetime);

        return $payload.'.'.$this->mac($payload);
    }

    /**
     * @throws VoteRefused when the token is not one this site signed, or has expired
     */
    public function read(string $token): VoteTicket
    {
        if (\strlen($token) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $token, $parts)) {
            throw VoteRefused::invalidToken();
        }

        if (!hash_equals($this->mac($parts['nonce'].'.'.$parts['expiry']), $parts['mac'])) {
            throw VoteRefused::invalidToken();
        }

        $expiry = (int) $parts['expiry'];

        if ($expiry <= $this->clock->now()->getTimestamp()) {
            throw VoteRefused::expiredToken();
        }

        return new VoteTicket($parts['nonce'], new DateTimeImmutable('@'.$expiry));
    }

    private function mac(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', self::CONTEXT.'|'.$payload, $this->secret, true));
    }

    private function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
