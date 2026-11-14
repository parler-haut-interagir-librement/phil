<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\GuestRequest;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * A request made without an account, waiting for its link to be opened.
 *
 * The link proves that the person reads the mailbox the request names: it carries the request's identifier and a
 * secret drawn at random, of which only a SHA-256 hash is kept. It works once, until its expiry; the address it was
 * sent to is kept only as long as the request.
 */
final readonly class GuestRequest
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public const string TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    public function __construct(
        public string $id,
        public GuestRequestKind $kind,
        public string $email,
        public string $tokenHash,
        public DateTimeImmutable $requestedAt,
        public DateTimeImmutable $expiresAt,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('A request identifier is 32 lowercase hexadecimal characters.');
        }

        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $tokenHash)) {
            throw InvalidPrivacyValue::because('A request keeps the SHA-256 of its secret, in hexadecimal.');
        }

        if ($expiresAt <= $requestedAt) {
            throw InvalidPrivacyValue::because('A request expires after it is made.');
        }
    }

    /**
     * A new request and the secret of its link, which is never kept.
     *
     * @return array{self, string}
     */
    public static function issue(GuestRequestKind $kind, string $email, DateTimeImmutable $now, DateTimeImmutable $expiresAt): array
    {
        $token = sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return [new self(sodium_bin2hex(random_bytes(16)), $kind, $email, self::hash($token), $now, $expiresAt), $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * Whether a secret is the one of this request's link, compared in constant time.
     */
    public function isOpenedBy(string $token): bool
    {
        return 1 === preg_match(self::TOKEN_PATTERN, $token) && hash_equals($this->tokenHash, self::hash($token));
    }
}
