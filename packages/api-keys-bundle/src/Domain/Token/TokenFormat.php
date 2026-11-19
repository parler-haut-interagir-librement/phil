<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Token;

use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Exception\MalformedToken;

/**
 * The text of a token: `<prefix>_<id>_<secret><checksum>`.
 *
 * - The prefix names the application, 2 to 16 lowercase letters and digits starting with a letter, so that a secret
 *   scanner recognises its tokens in a repository or a paste.
 * - The identifier, 12 letters and digits, finds the token's row; it is not secret.
 * - The secret, 32 letters and digits drawn from the system's secure source, about 190 bits.
 * - The checksum, 6 letters and digits, is the CRC-32 of everything before it: a token mistyped or cut short is
 *   refused offline, before any lookup, and a scanner tells a real token from a lookalike.
 *
 * Every token of a prefix has the same length, and underscores keep it one word for a double click.
 */
final readonly class TokenFormat
{
    public const string PREFIX_PATTERN = '/^[a-z][a-z0-9]{1,15}$/D';

    public const int ID_LENGTH = 12;

    public const int SECRET_LENGTH = 32;

    public const int CHECKSUM_LENGTH = 6;

    /**
     * @throws InvalidTokenValue when the prefix is not 2 to 16 lowercase letters and digits starting with a letter
     */
    public function __construct(
        public string $prefix,
    ) {
        if (1 !== preg_match(self::PREFIX_PATTERN, $prefix)) {
            throw InvalidTokenValue::because(\sprintf('A token prefix is 2 to 16 lowercase letters and digits, starting with a letter; "%s" is not.', $prefix));
        }
    }

    /**
     * The length of every token of this prefix.
     */
    public function length(): int
    {
        return \strlen($this->prefix) + 2 + self::ID_LENGTH + self::SECRET_LENGTH + self::CHECKSUM_LENGTH;
    }

    /**
     * A new identifier, drawn at random.
     */
    public function newId(): string
    {
        return Base62::random(self::ID_LENGTH);
    }

    /**
     * A new secret, drawn at random.
     */
    public function newSecret(): string
    {
        return Base62::random(self::SECRET_LENGTH);
    }

    /**
     * The text of the token of this identifier and secret, its checksum appended.
     */
    public function format(string $id, string $secret): string
    {
        $body = $this->prefix.'_'.$id.'_'.$secret;

        return $body.$this->checksum($body);
    }

    /**
     * Reads a token: its length, its prefix, its separators, its characters and its checksum, in that order, and
     * nothing else. The message of the exception never repeats the text.
     *
     * @throws MalformedToken when the text is not a token of this prefix
     */
    public function parse(string $text): PresentedToken
    {
        if (\strlen($text) !== $this->length()) {
            throw MalformedToken::because('it does not have the length of a token');
        }

        $start = \strlen($this->prefix) + 1;

        if (!str_starts_with($text, $this->prefix.'_') || '_' !== $text[$start + self::ID_LENGTH]) {
            throw MalformedToken::because('it does not have the prefix and separators of a token');
        }

        $id = substr($text, $start, self::ID_LENGTH);
        $secret = substr($text, $start + self::ID_LENGTH + 1, self::SECRET_LENGTH);
        $checksum = substr($text, -self::CHECKSUM_LENGTH);

        if (!Base62::isEncoded($id) || !Base62::isEncoded($secret) || !Base62::isEncoded($checksum)) {
            throw MalformedToken::because('it holds a character a token does not');
        }

        if (!hash_equals($this->checksum(substr($text, 0, -self::CHECKSUM_LENGTH)), $checksum)) {
            throw MalformedToken::because('its checksum does not match');
        }

        return new PresentedToken($id, $secret);
    }

    private function checksum(string $body): string
    {
        /** @var non-negative-int $crc a CRC-32 is 0 to 2³² − 1 on a 64-bit platform */
        $crc = crc32($body);

        return Base62::fixed($crc, self::CHECKSUM_LENGTH);
    }
}
