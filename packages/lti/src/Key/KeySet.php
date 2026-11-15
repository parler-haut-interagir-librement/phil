<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use JsonException;
use Phil\Lti\Exception\InvalidToken;

/**
 * A JSON Web Key Set of RSA signing keys, as a side publishes it. Reading one keeps the RSA keys for `RS256` and skips
 * the others — keys for encryption, of another type or algorithm —, so that a set shared with other uses still serves.
 */
final readonly class KeySet
{
    /**
     * The most keys read from a set: a published set holds a few, one or two per rotation.
     */
    public const int MAX_KEYS = 32;

    /**
     * The largest key set read, in bytes.
     */
    public const int MAX_BYTES = 262_144;

    /**
     * @param list<RsaPublicKey> $keys
     */
    public function __construct(
        public array $keys,
    ) {
        if (\count($keys) > self::MAX_KEYS) {
            throw InvalidToken::because(\sprintf('A key set holds %d keys at most.', self::MAX_KEYS));
        }
    }

    /**
     * @throws InvalidToken when the document is not a key set
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidToken::because(\sprintf('A key set is %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $document = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidToken::because('The key set is not JSON.', $jsonException);
        }

        $entries = \is_array($document) ? $document['keys'] ?? null : null;

        if (!\is_array($entries) || !array_is_list($entries)) {
            throw InvalidToken::because('A key set is an object whose "keys" is a list.');
        }

        $keys = [];

        foreach ($entries as $entry) {
            if (!\is_array($entry) || 'RSA' !== ($entry['kty'] ?? null) || ('RS256' !== ($entry['alg'] ?? 'RS256')) || ('sig' !== ($entry['use'] ?? 'sig'))) {
                continue;
            }

            if (self::MAX_KEYS === \count($keys)) {
                throw InvalidToken::because(\sprintf('A key set holds %d keys at most.', self::MAX_KEYS));
            }

            $keys[] = RsaPublicKey::fromJwk($entry);
        }

        return new self($keys);
    }

    /**
     * The key of this identifier; without one, the only key of the set. Null when none matches, or when the token names
     * no key and the set holds several.
     */
    public function find(?string $keyId): ?RsaPublicKey
    {
        if (null === $keyId) {
            return 1 === \count($this->keys) ? $this->keys[0] : null;
        }

        foreach ($this->keys as $key) {
            if ($key->keyId === $keyId) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return ['keys' => array_map(static fn (RsaPublicKey $key): array => $key->toJwk(), $this->keys)];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
