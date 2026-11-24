<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * How many times a normalized search found no question, in one language: a total and the day of the latest one, never
 * who searched, from where or when exactly.
 *
 * The identifier is the hash of the language and the search, so that counting is one update of one row.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_faq_unanswered_count', columns: ['locale', 'searches'])]
final class UnansweredSearch
{
    public const string TABLE = 'phil_faq_unanswered_search';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'locale', length: 16)]
        public readonly string $locale,
        #[ORM\Column(name: 'terms', length: 100)]
        public readonly string $query,
        #[ORM\Column(name: 'searches')]
        public readonly int $searches,
        #[ORM\Column(name: 'last_searched_on', type: 'date_immutable')]
        public readonly DateTimeImmutable $lastSearchedOn,
    ) {
    }

    /**
     * The identifier of a search in a language.
     */
    public static function idOf(string $locale, string $query): string
    {
        return hash('sha256', $locale."\0".$query);
    }

    /**
     * A count read back from a store.
     */
    public static function restored(string $locale, string $query, int $searches, DateTimeImmutable $lastSearchedOn): self
    {
        return new self(self::idOf($locale, $query), $locale, $query, $searches, $lastSearchedOn);
    }
}
