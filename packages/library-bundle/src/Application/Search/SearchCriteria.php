<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Search;

use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;

/**
 * What a search of the catalogue asks for: words in the name or the creators — or an ISBN —, a kind, a group of so many
 * players, a play no longer than so many minutes, the age of the youngest player, a language, and only the titles with a
 * copy on the shelf now.
 */
final readonly class SearchCriteria
{
    /** The longest text searched, in characters. */
    public const int TEXT = 100;

    public ?string $text;

    /**
     * @throws InvalidLibraryData when a value is out of its bounds
     */
    public function __construct(
        ?string $text = null,
        public ?TitleKind $kind = null,
        public ?int $players = null,
        public ?int $minutes = null,
        public ?int $age = null,
        public ?string $language = null,
        public bool $availableOnly = false,
    ) {
        $text = null === $text ? '' : trim($text);
        $this->text = '' === $text ? null : $text;

        if (null !== $this->text && (mb_strlen($this->text) > self::TEXT || !mb_check_encoding($this->text, 'UTF-8'))) {
            throw InvalidLibraryData::because(\sprintf('A search is at most %d characters.', self::TEXT));
        }

        if ((null !== $players && ($players < 1 || $players > Record::MAX_PLAYERS)) || (null !== $minutes && ($minutes < 1 || $minutes > Record::MAX_MINUTES)) || (null !== $age && ($age < 0 || $age > Record::MAX_AGE))) {
            throw InvalidLibraryData::because('A search asks for players, minutes and an age within the bounds of a record.');
        }

        if (null !== $language && 1 !== preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8}){0,3}$/D', $language)) {
            throw InvalidLibraryData::because('A search asks for a language by its tag, such as "fr" or "en-CA".');
        }
    }

    /**
     * The text as an ISBN-13, when it is an ISBN.
     */
    public function isbn(): ?string
    {
        if (null === $this->text) {
            return null;
        }

        try {
            return Record::isbn($this->text);
        } catch (InvalidLibraryData) {
            return null;
        }
    }
}
