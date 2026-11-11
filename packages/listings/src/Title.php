<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * The title of a listing: valid UTF-8, its runs of white space folded into single spaces and trimmed, no control or
 * format character left, 1 to {@see self::MAX_LENGTH} characters.
 *
 * Input longer than {@see self::MAX_INPUT_BYTES} bytes is refused before any pattern runs on it.
 */
final readonly class Title
{
    public const int MAX_LENGTH = 120;

    public const int MAX_INPUT_BYTES = 2048;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidListing
     */
    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_INPUT_BYTES || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidListing::because(\sprintf('A title is valid UTF-8 of at most %d bytes.', self::MAX_INPUT_BYTES));
        }

        $folded = trim((string) preg_replace('/[\s\x{00A0}\x{2028}\x{2029}]+/u', ' ', $value), ' ');

        if (1 === preg_match('/\p{C}/u', $folded)) {
            throw InvalidListing::because('A title holds no control or format character.');
        }

        $length = mb_strlen($folded, 'UTF-8');

        if (0 === $length || $length > self::MAX_LENGTH) {
            throw InvalidListing::because(\sprintf('A title is 1 to %d characters; this one has %d.', self::MAX_LENGTH, $length));
        }

        return new self($folded);
    }
}
