<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Phil\GuardianConsent\Exception\InvalidConfiguration;
use SensitiveParameter;

/**
 * The keyed hash a confirmation token is stored as: the HMAC-SHA-256 of the request identifier and the verifier under
 * the application's key, so that the link is signed by the application and bound to its request.
 *
 * The hash names its algorithm and version, `$hmac-sha256$v=1$` followed by 64 lowercase hexadecimal digits, 81
 * characters. The keys are an ordered list: the first hashes new tokens, and every key checks one, so that the links
 * issued under a key it replaced keep working until that key is dropped. Hashes are compared with `hash_equals()`.
 */
final readonly class TokenHasher
{
    public const int MIN_KEY_LENGTH = 32;

    /** The most keys: the active one and up to seven it replaced. */
    public const int MAX_KEYS = 8;

    public const string PREFIX = '$hmac-sha256$v=1$';

    public const int LENGTH = 81;

    /** What sets the hashes of this library apart from those of another under the same key. */
    private const string CONTEXT = "phil/guardian-consent\0";

    private string $active;

    /** @var non-empty-array<int|string, string> */
    private array $keys;

    /**
     * @param string $key         the active key, which hashes new tokens
     * @param string ...$previous the keys it replaced, which still check the tokens issued under them
     *
     * @throws InvalidConfiguration when a key is shorter than 32 bytes, a key is given twice, or more than eight are given
     */
    public function __construct(
        #[SensitiveParameter]
        string $key,
        #[SensitiveParameter]
        string ...$previous,
    ) {
        $keys = [$key, ...$previous];

        if (\count($keys) > self::MAX_KEYS) {
            throw InvalidConfiguration::because(\sprintf('At most %d keys hash the confirmation tokens: drop the oldest.', self::MAX_KEYS));
        }

        foreach ($keys as $one) {
            if (\strlen($one) < self::MIN_KEY_LENGTH) {
                throw InvalidConfiguration::because(\sprintf('A key of the confirmation tokens is at least %d bytes: read it from the secrets vault.', self::MIN_KEY_LENGTH));
            }
        }

        if (\count(array_unique($keys)) !== \count($keys)) {
            throw InvalidConfiguration::because('A key of the confirmation tokens is given twice.');
        }

        $this->active = $key;
        $this->keys = $keys;
    }

    /**
     * The hash under the active key, the one a new request keeps.
     *
     * @return non-empty-string
     */
    public function hash(ConfirmationToken $token): string
    {
        return $this->hmac($this->active, $token);
    }

    /**
     * Whether a stored hash is the token's under one of the keys. Every key is computed and compared, in a time that
     * depends neither on where the hashes differ nor on which key matched.
     */
    public function matches(string $hash, ConfirmationToken $token): bool
    {
        $matched = false;

        foreach ($this->keys as $key) {
            $matched = hash_equals($this->hmac($key, $token), $hash) || $matched;
        }

        return $matched;
    }

    /**
     * @return non-empty-string
     */
    private function hmac(#[SensitiveParameter] string $key, ConfirmationToken $token): string
    {
        return self::PREFIX.hash_hmac('sha256', self::CONTEXT.$token->requestId."\0".$token->verifier, $key);
    }
}
