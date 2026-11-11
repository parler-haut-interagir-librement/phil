<?php

declare(strict_types=1);

namespace Phil\Phone\Exception;

use RuntimeException;

/**
 * The metadata to import is not libphonenumber's, or its plans cannot be written.
 */
final class InvalidMetadata extends RuntimeException implements PhoneException
{
    public static function notXml(): self
    {
        return new self('The phone number metadata is not XML.');
    }

    public static function territory(string $id): self
    {
        return new self(\sprintf('The territory "%s" of the phone number metadata has no identifier or country code.', $id));
    }

    public static function unwritable(string $file): self
    {
        return new self(\sprintf('The phone number plan "%s" cannot be written.', $file));
    }
}
