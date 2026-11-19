<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\ValueObject;

use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;

/**
 * What a title of the catalogue is described by: its kind and name, its ISBN, who made it and who published it, its
 * year, and for a game the players, the length of a play and the youngest age it suits; its language and a summary.
 *
 * An ISBN is kept as ISBN-13, its hyphens and spaces left out, its check digit verified: an ISBN-10 is turned into the
 * ISBN-13 of the same book, so that both are found by either. The forms check it with Symfony's `Isbn` constraint
 * first, for the reader; the record holds the invariant.
 */
final readonly class Record
{
    public const int NAME = 255;

    public const int SUMMARY = 5000;

    public const int MAX_PLAYERS = 99;

    /** A week, in minutes: the longest play a record gives. */
    public const int MAX_MINUTES = 10_080;

    public const int MAX_AGE = 99;

    public string $name;

    public ?string $isbn;

    public ?string $creators;

    public ?string $publisher;

    public ?string $summary;

    /**
     * @throws InvalidLibraryData when a value is out of its bounds, the ISBN is not one, or a minimum is above its maximum
     */
    public function __construct(
        public TitleKind $kind,
        string $name,
        ?string $isbn = null,
        ?string $creators = null,
        ?string $publisher = null,
        public ?int $year = null,
        public ?int $minPlayers = null,
        public ?int $maxPlayers = null,
        public ?int $minMinutes = null,
        public ?int $maxMinutes = null,
        public ?int $minAge = null,
        public ?string $language = null,
        ?string $summary = null,
    ) {
        $this->name = $this->text($name, 'name', self::NAME) ?? throw InvalidLibraryData::because('A record has a name.');
        $this->isbn = null === $isbn || '' === trim($isbn) ? null : self::isbn($isbn);
        $this->creators = $this->text($creators, 'creators', self::NAME);
        $this->publisher = $this->text($publisher, 'publisher', self::NAME);
        $this->summary = $this->text($summary, 'summary', self::SUMMARY, true);
        $this->range($year, 'year', 1000, 9999);
        $this->range($minPlayers, 'minimum of players', 1, self::MAX_PLAYERS);
        $this->range($maxPlayers, 'maximum of players', 1, self::MAX_PLAYERS);
        $this->range($minMinutes, 'shortest play', 1, self::MAX_MINUTES);
        $this->range($maxMinutes, 'longest play', 1, self::MAX_MINUTES);
        $this->range($minAge, 'youngest age', 0, self::MAX_AGE);
        $this->ordered($minPlayers, $maxPlayers, 'players');
        $this->ordered($minMinutes, $maxMinutes, 'length of a play');

        if (null !== $language && 1 !== preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8}){0,3}$/D', $language)) {
            throw InvalidLibraryData::because('The language of a record is a language tag, such as "fr" or "en-CA".');
        }
    }

    /**
     * Whether a group of so many players can play it: unknown players fit any group.
     */
    public function fits(int $players): bool
    {
        return (null === $this->minPlayers || $this->minPlayers <= $players) && (null === $this->maxPlayers || $players <= $this->maxPlayers);
    }

    /**
     * The ISBN-13 of an ISBN-10 or ISBN-13, its hyphens and spaces left out.
     *
     * @throws InvalidLibraryData when it is neither
     */
    public static function isbn(string $isbn): string
    {
        $digits = strtoupper(str_replace(['-', ' '], '', trim($isbn)));

        if (1 !== preg_match('/^(\d{9}[\dX]|\d{13})$/D', $digits) || !self::checks($digits)) {
            throw InvalidLibraryData::because(\sprintf('"%s" is not an ISBN-10 or ISBN-13.', mb_strimwidth($isbn, 0, 20, '…')));
        }

        if (13 === \strlen($digits)) {
            return $digits;
        }

        $thirteen = '978'.substr($digits, 0, 9);

        return $thirteen.((10 - self::sum13($thirteen) % 10) % 10);
    }

    /**
     * Whether the check digit of an ISBN-10 or ISBN-13 is right: the digits weighed 10 to 1 add up to a multiple of 11,
     * `X` standing for 10; or weighed 1 and 3 in turn, to a multiple of 10.
     */
    private static function checks(string $digits): bool
    {
        if (13 === \strlen($digits)) {
            return 0 === self::sum13($digits) % 10;
        }

        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $sum += ('X' === $digit ? 10 : (int) $digit) * (10 - $position);
        }

        return 0 === $sum % 11;
    }

    /**
     * The digits weighed 1 and 3 in turn, from the first.
     */
    private static function sum13(string $digits): int
    {
        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $sum += (int) $digit * (0 === $position % 2 ? 1 : 3);
        }

        return $sum;
    }

    /**
     * @throws InvalidLibraryData when the text holds a control character or is too long
     */
    private function text(?string $value, string $what, int $max, bool $lines = false): ?string
    {
        $text = null === $value ? '' : trim($value);

        if ('' === $text) {
            return null;
        }

        $controls = $lines ? '/[^\P{Cc}\t\n\r]/u' : '/\p{Cc}/u';

        if (!mb_check_encoding($text, 'UTF-8') || 1 === preg_match($controls, $text) || mb_strlen($text) > $max) {
            throw InvalidLibraryData::because(\sprintf('The %s of a record is a text of at most %d characters, without control characters.', $what, $max));
        }

        return $text;
    }

    /**
     * @throws InvalidLibraryData when the number is out of its bounds
     */
    private function range(?int $value, string $what, int $min, int $max): void
    {
        if (null !== $value && ($value < $min || $value > $max)) {
            throw InvalidLibraryData::because(\sprintf('The %s of a record is from %d to %d, not %d.', $what, $min, $max, $value));
        }
    }

    /**
     * @throws InvalidLibraryData when the minimum is above the maximum
     */
    private function ordered(?int $min, ?int $max, string $what): void
    {
        if (null !== $min && null !== $max && $min > $max) {
            throw InvalidLibraryData::because(\sprintf('The %s of a record goes from its minimum to its maximum: %d is above %d.', $what, $min, $max));
        }
    }
}
