<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * Why a moderator rejected or removed a listing, as a key the application translates.
 *
 * Lowercase ASCII segments separated by ".", "_" or "-", at most {@see self::MAX_LENGTH} bytes.
 */
final readonly class ModerationReason
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

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
            throw InvalidListing::because(\sprintf('A moderation reason is 1 to %d lowercase ASCII segments separated by ".", "_" or "-".', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
