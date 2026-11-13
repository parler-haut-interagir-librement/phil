<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\ValueObject;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use SensitiveParameter;

/**
 * A key that signs and verifies embed tokens: an identifier the token names, and a secret of 32 bytes at least, which
 * never leaves the site.
 */
final readonly class SigningKey
{
    public const int MIN_SECRET = 32;

    private const string ID = '/^[A-Za-z0-9_-]{1,32}$/D';

    private function __construct(
        public string $id,
        #[SensitiveParameter]
        public string $secret,
    ) {
    }

    /**
     * @throws InvalidEmbed when the identifier is not 1 to 32 letters, digits, `_` or `-`, or the secret is too short
     */
    public static function of(string $id, #[SensitiveParameter] string $secret): self
    {
        if (1 !== preg_match(self::ID, $id)) {
            throw InvalidEmbed::because(\sprintf('The identifier of a key, "%s", is 1 to 32 letters, digits, "_" or "-".', $id));
        }

        if (\strlen($secret) < self::MIN_SECRET) {
            throw InvalidEmbed::because(\sprintf('The secret of the key "%s" holds %d bytes at least.', $id, self::MIN_SECRET));
        }

        return new self($id, $secret);
    }
}
