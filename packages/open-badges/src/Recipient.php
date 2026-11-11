<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use Phil\OpenBadges\Exception\InvalidBadge;
use SensitiveParameter;

/**
 * Whom a credential is issued to, as the recipient gives it when they ask for the credential — the library never reads
 * an account: a decentralized identifier, which becomes the subject's identifier, or an e-mail address or a URL, kept
 * only as a salted SHA-256 hash.
 */
final readonly class Recipient
{
    /**
     * @param array<string, list<array<string, bool|string>>|string> $fields
     */
    private function __construct(
        public ?string $did,
        private array $fields,
    ) {
    }

    public static function did(string $did): self
    {
        if (1 !== preg_match('/^did:[a-z0-9]+:[^\s<>"{}|\\\\^`]+$/D', $did)) {
            throw InvalidBadge::because(\sprintf('"%s" is not a decentralized identifier.', $did));
        }

        return new self($did, ['id' => $did]);
    }

    /**
     * An e-mail address, hashed with the salt — random bytes the caller draws, at least 8 characters.
     */
    public static function email(#[SensitiveParameter] string $email, string $salt): self
    {
        if (!str_contains($email, '@')) {
            throw InvalidBadge::because('An e-mail address holds an "@".');
        }

        return self::hashed('emailAddress', self::normalized('emailAddress', $email), $salt);
    }

    /**
     * A URL the recipient controls, hashed with the salt.
     */
    public static function url(#[SensitiveParameter] string $url, string $salt): self
    {
        Achievement::checkUrl($url, 'recipient');

        return self::hashed('url', $url, $salt);
    }

    /**
     * Whether the subject of a credential is the one who presents this identity: its decentralized identifier, or the
     * e-mail address or URL whose salted hash it holds, compared in constant time.
     *
     * @param array<array-key, mixed> $credentialSubject
     */
    public static function identifies(array $credentialSubject, #[SensitiveParameter] string $identity): bool
    {
        if (\is_string($credentialSubject['id'] ?? null) && hash_equals($credentialSubject['id'], $identity)) {
            return true;
        }

        return array_any((array) ($credentialSubject['identifier'] ?? []), static fn (mixed $object): bool => \is_array($object) && self::matches($object, $identity));
    }

    /**
     * The recipient as the fields of an Open Badges `AchievementSubject`.
     *
     * @return array<string, list<array<string, bool|string>>|string>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * @param array<array-key, mixed> $object
     */
    private static function matches(array $object, #[SensitiveParameter] string $identity): bool
    {
        $hash = $object['identityHash'] ?? null;
        $type = $object['identityType'] ?? null;

        if (!\is_string($hash) || !\is_string($type)) {
            return false;
        }

        if (true !== ($object['hashed'] ?? null)) {
            return hash_equals($hash, $identity);
        }

        $salt = \is_string($object['salt'] ?? null) ? $object['salt'] : '';

        return hash_equals(strtolower($hash), 'sha256$'.hash('sha256', self::normalized($type, $identity).$salt));
    }

    private static function normalized(string $type, #[SensitiveParameter] string $identity): string
    {
        return 'emailAddress' === $type ? mb_strtolower(trim($identity)) : $identity;
    }

    private static function hashed(string $type, #[SensitiveParameter] string $identity, string $salt): self
    {
        if (\strlen($salt) < 8) {
            throw InvalidBadge::because('The salt of a hashed identity is at least 8 characters.');
        }

        return new self(null, ['identifier' => [[
            'type' => 'IdentityObject',
            'identityHash' => 'sha256$'.hash('sha256', $identity.$salt),
            'identityType' => $type,
            'hashed' => true,
            'salt' => $salt,
        ]]]);
    }
}
