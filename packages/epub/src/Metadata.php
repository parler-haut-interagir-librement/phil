<?php

declare(strict_types=1);

namespace Phil\Epub;

use DateTimeImmutable;
use Phil\Epub\Accessibility\Accessibility;
use Phil\Epub\Exception\InvalidBook;

/**
 * The metadata of the publication: its title, its language, its unique identifier and the moment it was last modified,
 * which EPUB requires, then its creators, contributors, publisher, description, publication date, subjects, rights,
 * source, other identifiers, direction and accessibility.
 */
final readonly class Metadata
{
    private const string LANGUAGE = '/^[A-Za-z]{2,8}(-[A-Za-z0-9]{1,8})*$/D';

    private const string DATE = '/^\d{4}(-\d{2}(-\d{2})?)?$/D';

    /**
     * @param list<Creator>    $creators
     * @param list<Creator>    $contributors
     * @param list<string>     $subjects
     * @param list<Identifier> $otherIdentifiers
     *
     * @throws InvalidBook
     */
    public function __construct(
        public string $title,
        public string $language,
        public Identifier $identifier,
        public DateTimeImmutable $modified,
        public array $creators = [],
        public array $contributors = [],
        public ?string $publisher = null,
        public ?string $description = null,
        public ?string $published = null,
        public array $subjects = [],
        public ?string $rights = null,
        public ?string $source = null,
        public array $otherIdentifiers = [],
        public Direction $direction = Direction::Default,
        public Accessibility $accessibility = new Accessibility(),
    ) {
        if ('' === trim($title)) {
            throw InvalidBook::because('A book needs a title.');
        }

        self::checkLanguage($language);

        if (null !== $published && 1 !== preg_match(self::DATE, $published)) {
            throw InvalidBook::because(\sprintf('The publication date is a year, a year and month, or a full date (YYYY-MM-DD); "%s" given.', $published));
        }
    }

    /**
     * @throws InvalidBook
     */
    public static function checkLanguage(string $language): void
    {
        if (1 !== preg_match(self::LANGUAGE, $language)) {
            throw InvalidBook::because(\sprintf('"%s" is not a language tag such as "en" or "fr-CA".', $language));
        }
    }
}
