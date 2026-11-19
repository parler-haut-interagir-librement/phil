<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The identifier of a person or an organization that never changes: the immutable identifier of an account, or the
 * `sub` claim of its identity provider — **never the identifier a user signs in with**, an email address or a
 * username, which the person may change and someone else may take after them.
 *
 * It is 1 to 255 visible ASCII characters without `@`: an email address is refused, so that a gift or a seat never
 * follows an address to whoever holds it next.
 */
final readonly class StableIdentifier
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value, string $what): self
    {
        if (1 !== preg_match('/^[\x21-\x3F\x41-\x7E]{1,255}$/D', $value)) {
            throw InvalidValue::because(\sprintf('"%s" is not the stable identifier of a %s: 1 to 255 visible ASCII characters without "@", never an email address or a username.', $value, $what));
        }

        return new self($value);
    }
}
