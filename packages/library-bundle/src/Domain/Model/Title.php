<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Symfony\Component\Uid\Uuid;

/**
 * A title of the catalogue — a game, a book —, described by its record; its copies are the objects lent. The record's
 * fields are columns of their own, so that a search filters on them.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_library_title_name', columns: ['name'])]
#[ORM\Index(name: 'phil_library_title_isbn', columns: ['isbn'])]
final class Title
{
    public const string TABLE = 'phil_library_title';

    #[ORM\Column(name: 'kind', length: 16, enumType: TitleKind::class)]
    private TitleKind $kind;

    #[ORM\Column(name: 'name', length: 255)]
    private string $name;

    #[ORM\Column(name: 'isbn', length: 13, nullable: true, options: ['fixed' => true])]
    private ?string $isbn;

    #[ORM\Column(name: 'creators', length: 255, nullable: true)]
    private ?string $creators;

    #[ORM\Column(name: 'publisher', length: 255, nullable: true)]
    private ?string $publisher;

    #[ORM\Column(name: 'year', type: 'smallint', nullable: true)]
    private ?int $year;

    #[ORM\Column(name: 'min_players', type: 'smallint', nullable: true)]
    private ?int $minPlayers;

    #[ORM\Column(name: 'max_players', type: 'smallint', nullable: true)]
    private ?int $maxPlayers;

    #[ORM\Column(name: 'min_minutes', type: 'integer', nullable: true)]
    private ?int $minMinutes;

    #[ORM\Column(name: 'max_minutes', type: 'integer', nullable: true)]
    private ?int $maxMinutes;

    #[ORM\Column(name: 'min_age', type: 'smallint', nullable: true)]
    private ?int $minAge;

    #[ORM\Column(name: 'language', length: 35, nullable: true)]
    private ?string $language;

    #[ORM\Column(name: 'summary', type: 'text', nullable: true)]
    private ?string $summary;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        Record $record,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
        $this->describe($record);
    }

    public static function catalogue(Record $record, DateTimeImmutable $now): self
    {
        return new self(Uuid::v7()->toRfc4122(), $record, $now);
    }

    /**
     * Replaces its record.
     */
    public function describe(Record $record): void
    {
        $this->kind = $record->kind;
        $this->name = $record->name;
        $this->isbn = $record->isbn;
        $this->creators = $record->creators;
        $this->publisher = $record->publisher;
        $this->year = $record->year;
        $this->minPlayers = $record->minPlayers;
        $this->maxPlayers = $record->maxPlayers;
        $this->minMinutes = $record->minMinutes;
        $this->maxMinutes = $record->maxMinutes;
        $this->minAge = $record->minAge;
        $this->language = $record->language;
        $this->summary = $record->summary;
    }

    public function record(): Record
    {
        return new Record($this->kind, $this->name, $this->isbn, $this->creators, $this->publisher, $this->year, $this->minPlayers, $this->maxPlayers, $this->minMinutes, $this->maxMinutes, $this->minAge, $this->language, $this->summary);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): TitleKind
    {
        return $this->kind;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
