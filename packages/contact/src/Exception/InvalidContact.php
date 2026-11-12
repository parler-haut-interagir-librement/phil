<?php

declare(strict_types=1);

namespace Phil\Contact\Exception;

use InvalidArgumentException;

/**
 * An email address that is none, a text too long for its card, or a person name or JSContact card that cannot be made.
 */
final class InvalidContact extends InvalidArgumentException implements ContactException
{
    public static function email(string $input): self
    {
        return new self(\sprintf('"%s" is not an email address.', mb_substr($input, 0, 100)));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function tooLong(string $field): self
    {
        return new self(\sprintf('The %s of a contact card is 255 characters or fewer.', $field));
    }
}
