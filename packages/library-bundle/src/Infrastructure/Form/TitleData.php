<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Form;

use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;

/**
 * The fields of the record form, as typed: the form edits them, `record()` checks them into a record.
 */
final class TitleData
{
    public TitleKind $kind = TitleKind::Game;

    public ?string $name = null;

    public ?string $isbn = null;

    public ?string $creators = null;

    public ?string $publisher = null;

    public ?int $year = null;

    public ?int $minPlayers = null;

    public ?int $maxPlayers = null;

    public ?int $minMinutes = null;

    public ?int $maxMinutes = null;

    public ?int $minAge = null;

    public ?string $language = null;

    public ?string $summary = null;

    public static function of(Record $record): self
    {
        $data = new self();
        $data->kind = $record->kind;
        $data->name = $record->name;
        $data->isbn = $record->isbn;
        $data->creators = $record->creators;
        $data->publisher = $record->publisher;
        $data->year = $record->year;
        $data->minPlayers = $record->minPlayers;
        $data->maxPlayers = $record->maxPlayers;
        $data->minMinutes = $record->minMinutes;
        $data->maxMinutes = $record->maxMinutes;
        $data->minAge = $record->minAge;
        $data->language = $record->language;
        $data->summary = $record->summary;

        return $data;
    }

    /**
     * @throws InvalidLibraryData when a field is not one a record takes
     */
    public function record(): Record
    {
        return new Record($this->kind, $this->name ?? '', $this->isbn, $this->creators, $this->publisher, $this->year, $this->minPlayers, $this->maxPlayers, $this->minMinutes, $this->maxMinutes, $this->minAge, $this->language, $this->summary);
    }
}
