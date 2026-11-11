<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;

/**
 * Where a listing takes place: online, or in an area shown to everyone — a city, a neighbourhood — with an exact
 * place shown only to the author and to the respondents the author accepted.
 *
 * Both labels are folded and bounded like a {@see Title}: valid UTF-8, single spaces, no control character.
 */
final readonly class Location
{
    public const int MAX_AREA_LENGTH = 120;

    public const int MAX_EXACT_LENGTH = 255;

    private function __construct(
        public ?string $area,
        public ?string $exact,
    ) {
    }

    public static function online(): self
    {
        return new self(null, null);
    }

    /**
     * @throws InvalidListing
     */
    public static function area(string $area, ?string $exact = null): self
    {
        return new self(self::label($area, self::MAX_AREA_LENGTH), null === $exact ? null : self::label($exact, self::MAX_EXACT_LENGTH));
    }

    public function isOnline(): bool
    {
        return null === $this->area;
    }

    /**
     * The location as anyone may see it: the area without the exact place.
     */
    public function approximate(): self
    {
        return new self($this->area, null);
    }

    private static function label(string $label, int $max): string
    {
        if (\strlen($label) > 4 * $max || !mb_check_encoding($label, 'UTF-8')) {
            throw InvalidListing::because(\sprintf('A place is valid UTF-8 of at most %d characters.', $max));
        }

        $folded = trim((string) preg_replace('/[\s\x{00A0}\x{2028}\x{2029}]+/u', ' ', $label), ' ');
        $length = mb_strlen($folded, 'UTF-8');

        if (1 === preg_match('/\p{C}/u', $folded) || 0 === $length || $length > $max) {
            throw InvalidListing::because(\sprintf('A place is 1 to %d characters, without control or format character.', $max));
        }

        return $folded;
    }
}
