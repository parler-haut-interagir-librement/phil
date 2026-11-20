<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Token;

use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;

/**
 * Who a token acts for: a type the configuration declares — `user`, a member, by default; `organization`,
 * `application` or any other — and the identifier the user provider of that type loads it by.
 */
final readonly class TokenOwner
{
    /** The owner type of members, loaded by the firewall's own user provider. */
    public const string USER = 'user';

    public const string TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/D';

    public const int TYPE_MAX_LENGTH = 32;

    public const int ID_MAX_LENGTH = 180;

    /**
     * @throws InvalidTokenValue when the type is not a lowercase letter followed by up to 31 lowercase letters, digits
     *                           or "_", or the identifier is not 1 to 180 bytes
     */
    public function __construct(
        public string $type,
        public string $id,
    ) {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw InvalidTokenValue::because('The type of a token owner is a lowercase letter followed by up to 31 lowercase letters, digits or "_".');
        }

        if ('' === $id || \strlen($id) > self::ID_MAX_LENGTH) {
            throw InvalidTokenValue::because(\sprintf('The owner of a token is 1 to %d bytes.', self::ID_MAX_LENGTH));
        }
    }

    /**
     * A member, by the identifier the firewall's user provider loads them by.
     *
     * @throws InvalidTokenValue when the identifier is not 1 to 180 bytes
     */
    public static function user(string $id): self
    {
        return new self(self::USER, $id);
    }

    /**
     * The owner itself, or a member by their identifier.
     *
     * @throws InvalidTokenValue when the identifier is not 1 to 180 bytes
     */
    public static function of(self|string $owner): self
    {
        return $owner instanceof self ? $owner : self::user($owner);
    }

    public function isUser(): bool
    {
        return self::USER === $this->type;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
