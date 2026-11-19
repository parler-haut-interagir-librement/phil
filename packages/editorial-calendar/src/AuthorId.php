<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

use Phil\EditorialCalendar\Exception\InvalidItem;

/**
 * The stable identifier of an author — a user identifier, a UUID —, never an email address: an address changes and is
 * personal data, an identifier is neither.
 */
final readonly class AuthorId
{
    /**
     * @throws InvalidItem when the value is not 1 to 128 letters, digits, ".", "_", ":" or "-"
     */
    public function __construct(
        public string $value,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $value)) {
            throw InvalidItem::because(\sprintf('An author is named by a stable identifier of 1 to 128 letters, digits, ".", "_", ":" or "-", never by an address; "%s" is not one.', $value));
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
