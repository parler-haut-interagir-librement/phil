<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Exception\InvalidOwner;
use Stringable;

/**
 * The stable identifier of the owner of a record, such as a UUID. An owner outlives what it owns and is copied into
 * every row it owns, so an e-mail address is refused here: nothing with an `@` becomes an owner.
 */
final readonly class OwnerId implements Stringable
{
    public const int MAX_LENGTH = 255;

    /**
     * @throws InvalidOwner when the identifier is empty, longer than 255 bytes, or holds an `@`
     */
    public function __construct(
        public string $value,
    ) {
        if ('' === $value) {
            throw InvalidOwner::empty();
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidOwner::tooLong(self::MAX_LENGTH);
        }

        if (str_contains($value, '@')) {
            throw InvalidOwner::eMail();
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
