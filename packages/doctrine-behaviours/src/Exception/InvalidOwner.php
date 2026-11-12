<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use InvalidArgumentException;

/**
 * What cannot be the identifier of an owner. The message never repeats the value: it may be someone's e-mail address.
 */
final class InvalidOwner extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('The identifier of an owner cannot be empty.');
    }

    public static function tooLong(int $limit): self
    {
        return new self(\sprintf('The identifier of an owner is at most %d bytes long.', $limit));
    }

    public static function eMail(): self
    {
        return new self('The identifier of an owner holds an @: name owners by a stable identifier, such as a UUID, not by an e-mail address.');
    }
}
