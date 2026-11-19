<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use SensitiveParameter;

/**
 * The keyed hash a token's row keeps in place of its secret: the HMAC-SHA-256 of the identifier and the secret under
 * the application's key. A copy of the table opens nothing without the key, and the key alone opens nothing either.
 *
 * The hash names its algorithm and the version of what is hashed, in the shape of the PHC string format:
 * `$hmac-sha256$v=1$<the 32 bytes in Base64 without padding>`, 60 characters. A hash of another algorithm or version
 * matches nothing, so that a later one can be told apart and the rows moved to it.
 *
 * The key rotates: a new token is hashed under the active key, the first one; a presented token is checked against the
 * active key and every previous one, so that the tokens issued under a previous key keep working until it is dropped.
 * Every token expires, so a previous key can be dropped once the longest lifetime has passed since it was replaced.
 */
final readonly class TokenHasher
{
    public const int MIN_KEY_LENGTH = 32;

    /** The most keys checked: the active one and up to seven previous ones. */
    public const int MAX_KEYS = 8;

    public const string ALGORITHM = 'hmac-sha256';

    /** The version of what is hashed: the identifier, an underscore and the secret. */
    public const int VERSION = 1;

    /** What every hash of this algorithm and version starts with. */
    public const string PREFIX = '$'.self::ALGORITHM.'$v='.self::VERSION.'$';

    /** The length of a hash: its prefix and 43 characters of Base64. */
    public const int LENGTH = 60;

    /** @var non-empty-list<string> */
    private array $keys;

    /**
     * @param string $key         the active key, which hashes new tokens
     * @param string ...$previous the keys it replaced, which still verify the tokens issued under them
     *
     * @throws InvalidTokenValue when a key is shorter than 32 bytes, a key is given twice, or more than eight are given
     */
    public function __construct(
        #[SensitiveParameter]
        string $key,
        #[SensitiveParameter]
        string ...$previous,
    ) {
        $keys = [$key];

        foreach ($previous as $one) {
            $keys[] = $one;
        }

        if (\count($keys) > self::MAX_KEYS) {
            throw InvalidTokenValue::because(\sprintf('At most %d keys hash the tokens: drop the oldest.', self::MAX_KEYS));
        }

        foreach ($keys as $one) {
            if (\strlen($one) < self::MIN_KEY_LENGTH) {
                throw InvalidTokenValue::because(\sprintf('The key of the token hashes is at least %d bytes: read it from the secrets vault.', self::MIN_KEY_LENGTH));
            }
        }

        if (\count(array_unique($keys)) < \count($keys)) {
            throw InvalidTokenValue::because('A key of the token hashes is given twice.');
        }

        $this->keys = $keys;
    }

    /**
     * The hash under the active key.
     *
     * @return non-empty-string `$hmac-sha256$v=1$` and 43 characters of Base64
     */
    public function hash(string $id, #[SensitiveParameter] string $secret): string
    {
        return $this->hmac($this->keys[0], $id, $secret);
    }

    /**
     * Whether the secret matches the hash under one of the keys. Every key is computed and compared, in a time that
     * depends neither on where the hashes differ nor on which key matched.
     */
    public function matches(string $hash, string $id, #[SensitiveParameter] string $secret): bool
    {
        $matched = false;

        foreach ($this->keys as $key) {
            $matched = hash_equals($hash, $this->hmac($key, $id, $secret)) || $matched;
        }

        return $matched;
    }

    /**
     * @return non-empty-string
     */
    private function hmac(#[SensitiveParameter] string $key, string $id, #[SensitiveParameter] string $secret): string
    {
        return self::PREFIX.sodium_bin2base64(hash_hmac('sha256', $id.'_'.$secret, $key, true), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
    }
}
