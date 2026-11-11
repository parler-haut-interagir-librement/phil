<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * The identifier of one response to a listing, chosen by the application: a UUID, a ULID, a database key written as text.
 *
 * ASCII letters, digits and ".", "_", ":" or "-", starting with a letter or a digit, at most
 * {@see self::MAX_LENGTH} bytes. It carries no personal data, so that it can travel in a message.
 */
final readonly class ResponseId
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidListing
     */
    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidListing::because(\sprintf('An identifier is 1 to %d ASCII letters, digits, ".", "_", ":" or "-".', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
