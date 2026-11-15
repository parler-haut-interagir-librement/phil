<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use SodiumException;

/**
 * The links of the newsletter's messages and pages — confirm, unsubscribe, preferences, click —, signed so that nobody
 * can make one for another subscriber: the fields, separated by `|`, then an HMAC-SHA256 of them under the
 * application's key, cut to 128 bits, both in URL-safe base64.
 *
 * A link holds identifiers only: a subscriber's id, a list's or a campaign's, a link index. It is checked in constant
 * time, refused when it is longer than 512 characters, malformed, for another purpose, or expired.
 */
final readonly class SignedLinks
{
    public const int MAX_LENGTH = 512;

    public function __construct(
        #[SensitiveParameter]
        private string $key,
        private ClockInterface $clock,
    ) {
        if (\strlen($key) < 16) {
            throw LinkRefused::because('The key of the newsletter links holds at least 16 bytes.');
        }
    }

    /**
     * @param int|null $lifetime seconds the link is valid for; `null` for a link that does not expire
     */
    public function sign(LinkPurpose $purpose, string $subject, string $extra = '', ?int $lifetime = null): string
    {
        if (str_contains($subject.$extra, '|')) {
            throw LinkRefused::because('A field of a link holds no "|".');
        }

        $now = $this->clock->now()->getTimestamp();
        $payload = implode('|', [$purpose->value, $subject, $extra, (string) $now, null === $lifetime ? '' : (string) ($now + $lifetime)]);

        return $this->encode($payload).'.'.$this->encode($this->mac($payload));
    }

    /**
     * @throws LinkRefused when the link is not one of the newsletter's for this purpose, or expired
     */
    public function verify(string $token, LinkPurpose $purpose): SignedLink
    {
        if (\strlen($token) > self::MAX_LENGTH || 1 !== preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{22})$/D', $token, $parts)) {
            throw LinkRefused::because('The link is malformed.');
        }

        $payload = $this->decode($parts[1]);

        if (null === $payload || !hash_equals($this->encode($this->mac($payload)), $parts[2])) {
            throw LinkRefused::because('The link is not signed by the newsletter.');
        }

        $fields = explode('|', $payload);

        if (5 !== \count($fields) || $purpose->value !== $fields[0] || 1 !== preg_match('/^[0-9]{1,12}$/D', $fields[3]) || 1 !== preg_match('/^([0-9]{1,12})?$/D', $fields[4])) {
            throw LinkRefused::because('The link is for something else.');
        }

        $expiresAt = '' === $fields[4] ? null : (int) $fields[4];

        if (null !== $expiresAt && $expiresAt <= $this->clock->now()->getTimestamp()) {
            throw LinkRefused::because('The link has expired.');
        }

        return new SignedLink($purpose, $fields[1], $fields[2], (int) $fields[3], $expiresAt);
    }

    private function mac(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, $this->key, true), 0, 16);
    }

    private function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function decode(string $text): ?string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return null;
        }
    }
}
