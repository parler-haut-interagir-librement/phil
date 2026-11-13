<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Read\DocumentInformation;

/**
 * Writes the document information a viewer shows; a field left unset keeps its value. A date is written as a PDF date
 * in UTC, or passed as it is when it is already text.
 */
final readonly class SetMetadata implements Stage
{
    public ?string $creationDate;

    public ?string $modDate;

    public function __construct(
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $creator = null,
        public ?string $producer = null,
        DateTimeInterface|string|null $creationDate = null,
        DateTimeInterface|string|null $modDate = null,
    ) {
        $this->creationDate = $this->date($creationDate);
        $this->modDate = $this->date($modDate);

        if ([] === $this->options()) {
            throw InvalidStage::because('Setting the metadata takes one field at least.');
        }
    }

    /**
     * The information another PDF holds, as `/forms/pdf/metadata` read it.
     */
    public static function from(DocumentInformation $information): self
    {
        return new self($information->title, $information->author, $information->subject, $information->keywords, $information->creator, $information->producer, $information->creationDate, $information->modDate);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::SetMetadata;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'title' => $this->title,
            'author' => $this->author,
            'subject' => $this->subject,
            'keywords' => $this->keywords,
            'creator' => $this->creator,
            'producer' => $this->producer,
            'creationDate' => $this->creationDate,
            'modDate' => $this->modDate,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }

    private function date(DateTimeInterface|string|null $date): ?string
    {
        if (!$date instanceof DateTimeInterface) {
            return $date;
        }

        $utc = new DateTimeZone('UTC');

        return 'D:'.DateTimeImmutable::createFromInterface($date)->setTimezone($utc)->format('YmdHis').'Z';
    }
}
