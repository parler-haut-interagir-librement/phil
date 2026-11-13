<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use Phil\AccessLinks\Exception\InvalidConfiguration;
use SensitiveParameter;

/**
 * The keyed hash a link token is stored as: the HMAC-SHA-256 of the token under the application's key. A copy of the
 * table opens no link without the key.
 *
 * The hash names its algorithm and version in the shape of the PHC string format,
 * `$hmac-sha256$v=1$<the 32 bytes in lowercase hexadecimal>`, 81 characters: hexadecimal compares the same under
 * a case-insensitive collation, the default of MySQL.
 *
 * The keys are an ordered list: the first hashes new tokens, and every key finds a token, so that the links issued
 * under a key it replaced keep opening until that key is dropped. A token is looked up by its hash under each key at
 * once; the stores compare hashes by an index or a hash table, whose timing says nothing of a token since the hash is
 * keyed, and `matches()` compares with `hash_equals()`.
 */
final readonly class TokenHasher
{
    public const int MIN_KEY_LENGTH = 32;

    /** The most keys: the active one and up to seven it replaced. */
    public const int MAX_KEYS = 8;

    public const string PREFIX = '$hmac-sha256$v=1$';

    /** The length of a hash: its prefix and 64 hexadecimal digits. */
    public const int LENGTH = 81;

    /** What sets the hashes of this library apart from those of another under the same key. */
    private const string CONTEXT = "phil/access-links\0";

    /** @var non-empty-list<string> */
    private array $keys;

    /**
     * @param string $key         the active key, which hashes new tokens
     * @param string ...$previous the keys it replaced, which still find the tokens issued under them
     *
     * @throws InvalidConfiguration when a key is shorter than 32 bytes, a key is given twice, or more than eight are given
     */
    public function __construct(
        #[SensitiveParameter]
        string $key,
        #[SensitiveParameter]
        string ...$previous,
    ) {
        $keys = [$key, ...array_values($previous)];

        if (\count($keys) > self::MAX_KEYS) {
            throw InvalidConfiguration::because(\sprintf('At most %d keys hash the link tokens: drop the oldest.', self::MAX_KEYS));
        }

        foreach ($keys as $one) {
            if (\strlen($one) < self::MIN_KEY_LENGTH) {
                throw InvalidConfiguration::because(\sprintf('A key of the link tokens is at least %d bytes: read it from the secrets vault.', self::MIN_KEY_LENGTH));
            }
        }

        if (\count(array_unique($keys)) < \count($keys)) {
            throw InvalidConfiguration::because('A key of the link tokens is given twice.');
        }

        $this->keys = $keys;
    }

    /**
     * The hash under the active key, the one a new token is stored as.
     *
     * @return non-empty-string
     */
    public function hash(#[SensitiveParameter] string $token): string
    {
        return $this->hmac($this->keys[0], $token);
    }

    /**
     * The hash under every key, the active one first: what a token is looked up by.
     *
     * @return non-empty-list<non-empty-string>
     */
    public function candidates(#[SensitiveParameter] string $token): array
    {
        return array_map(fn (string $key): string => $this->hmac($key, $token), $this->keys);
    }

    /**
     * Whether a stored hash is the token's under one of the keys. Every key is computed and compared, in a time that
     * depends neither on where the hashes differ nor on which key matched.
     */
    public function matches(string $hash, #[SensitiveParameter] string $token): bool
    {
        $matched = false;

        foreach ($this->candidates($token) as $candidate) {
            $matched = hash_equals($hash, $candidate) || $matched;
        }

        return $matched;
    }

    /**
     * @return non-empty-string
     */
    private function hmac(#[SensitiveParameter] string $key, #[SensitiveParameter] string $token): string
    {
        return self::PREFIX.hash_hmac('sha256', self::CONTEXT.$token, $key);
    }
}
