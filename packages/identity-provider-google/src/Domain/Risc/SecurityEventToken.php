<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Domain\Risc;

use Phil\IdentityProviderGoogle\Domain\Exception\InvalidSecurityEventToken;

/**
 * The claims of a security event token (RFC 8417), read once its signature holds: who issued it, for whom, when, its
 * identifier, and the events it carries — those of a known type, in the order of the token.
 *
 * The reading is bounded: at most 16 events, identifiers of at most 255 bytes, issuers of at most 2048. A token with
 * no `events` object, such as an ID token, is refused, so that a token of another kind is never taken for an event.
 */
final readonly class SecurityEventToken
{
    public const int MAX_EVENTS = 16;

    public const int MAX_IDENTIFIER_LENGTH = 255;

    public const int MAX_ISSUER_LENGTH = 2048;

    /**
     * @param list<string>        $audiences
     * @param list<SecurityEvent> $events    the events of a known type
     */
    private function __construct(
        public string $issuer,
        public string $id,
        public int $issuedAt,
        public array $audiences,
        public array $events,
    ) {
    }

    /**
     * @param array<array-key, mixed> $claims the payload of the token, decoded
     *
     * @throws InvalidSecurityEventToken when a claim is missing, of the wrong type or out of its bounds
     */
    public static function fromClaims(array $claims): self
    {
        $issuer = self::string($claims, 'iss', self::MAX_ISSUER_LENGTH) ?? throw InvalidSecurityEventToken::malformed('The token has no "iss".');
        $id = self::string($claims, 'jti', self::MAX_IDENTIFIER_LENGTH) ?? throw InvalidSecurityEventToken::malformed('The token has no "jti".');
        $issuedAt = $claims['iat'] ?? null;

        if (!\is_int($issuedAt) || $issuedAt < 0) {
            throw InvalidSecurityEventToken::malformed('The token has no "iat" of whole seconds.');
        }

        $events = $claims['events'] ?? null;

        if (!\is_array($events) || [] === $events || array_is_list($events)) {
            throw InvalidSecurityEventToken::malformed('The token has no "events" object.');
        }

        if (\count($events) > self::MAX_EVENTS) {
            throw InvalidSecurityEventToken::malformed(\sprintf('The token carries more than %d events.', self::MAX_EVENTS));
        }

        $fallback = \is_array($claims['sub_id'] ?? null) ? $claims['sub_id'] : null;
        $read = [];

        foreach ($events as $uri => $event) {
            if (!\is_array($event)) {
                throw InvalidSecurityEventToken::malformed('An event of the token is not an object.');
            }

            $type = SecurityEventType::tryFrom((string) $uri);

            if ($type instanceof SecurityEventType) {
                $read[] = self::event($type, $event, $fallback);
            }
        }

        return new self($issuer, $id, $issuedAt, self::audiences($claims['aud'] ?? null), $read);
    }

    /**
     * @return list<string>
     */
    private static function audiences(mixed $audience): array
    {
        $audiences = \is_string($audience) ? [$audience] : $audience;

        if (!\is_array($audiences) || [] === $audiences || \count($audiences) > self::MAX_EVENTS) {
            throw InvalidSecurityEventToken::malformed('The token has no "aud".');
        }

        $read = [];

        foreach ($audiences as $one) {
            if (!\is_string($one) || '' === $one || \strlen($one) > self::MAX_ISSUER_LENGTH) {
                throw InvalidSecurityEventToken::malformed('An audience of the token is not a string.');
            }

            $read[] = $one;
        }

        return $read;
    }

    /**
     * @param array<array-key, mixed>      $event
     * @param array<array-key, mixed>|null $fallback the subject of the whole token, `sub_id`
     */
    private static function event(SecurityEventType $type, array $event, ?array $fallback): SecurityEvent
    {
        $subject = \is_array($event['subject'] ?? null) ? $event['subject'] : $fallback;
        $format = null === $subject ? null : ($subject['subject_type'] ?? $subject['format'] ?? null);
        $issuer = null;
        $identifier = null;

        if (null !== $subject && \in_array($format, ['iss-sub', 'iss_sub'], true)) {
            $issuer = self::string($subject, 'iss', self::MAX_ISSUER_LENGTH) ?? throw InvalidSecurityEventToken::malformed('The subject of an event has no "iss".');
            $identifier = self::string($subject, 'sub', self::MAX_IDENTIFIER_LENGTH) ?? throw InvalidSecurityEventToken::malformed('The subject of an event has no "sub".');
        }

        return new SecurityEvent(
            $type,
            $issuer,
            $identifier,
            self::string($event, 'reason', self::MAX_IDENTIFIER_LENGTH),
            self::string($event, 'state', self::MAX_IDENTIFIER_LENGTH),
        );
    }

    /**
     * The claim when it is a string of 1 to `$max` bytes; null when it is absent.
     *
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidSecurityEventToken when it is present but not such a string
     */
    private static function string(array $claims, string $name, int $max): ?string
    {
        if (!\array_key_exists($name, $claims)) {
            return null;
        }

        $value = $claims[$name];

        if (!\is_string($value) || '' === $value || \strlen($value) > $max) {
            throw InvalidSecurityEventToken::malformed(\sprintf('The claim "%s" is not a string of 1 to %d bytes.', $name, $max));
        }

        return $value;
    }
}
