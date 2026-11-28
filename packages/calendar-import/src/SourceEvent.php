<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Url;

/**
 * An event as a source gives it, read and checked: its identity (UID, and the occurrence it changes, if any), its
 * version, its times in their zone, its texts as plain text, and its recurrence. It belongs to no calendar of the
 * application yet: the importer turns it into an `ImportedDraft`.
 */
final readonly class SourceEvent
{
    /**
     * @param ?DateTimeImmutable      $recurrenceId the start of the occurrence of a repeating event this one changes
     *                                              (`RECURRENCE-ID`), or null for the event itself
     * @param DateTimeImmutable       $end          the end, exclusive: `DTEND`, the start and `DURATION`, the next day
     *                                              for a whole day, or the start for an instant
     * @param bool                    $floating     whether the times were written without a zone, and were read in
     *                                              the calendar's (`X-WR-TIMEZONE`) or the source's
     * @param list<string>            $tags         the `CATEGORIES`, each once
     * @param list<DateTimeImmutable> $additions    the `RDATE` dates
     * @param list<DateTimeImmutable> $exclusions   the `EXDATE` dates
     */
    public function __construct(
        public string $uid,
        public ?DateTimeImmutable $recurrenceId,
        public int $sequence,
        public ?DateTimeImmutable $lastModified,
        public ?DateTimeImmutable $stamp,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay,
        public bool $floating,
        public DateTimeZone $timezone,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?Url $url = null,
        public EventStatus $status = EventStatus::Confirmed,
        public array $tags = [],
        public ?RecurrenceRule $rule = null,
        public array $additions = [],
        public array $exclusions = [],
    ) {
    }

    /**
     * The key of the event in its source: its UID, or, for a changed occurrence, its UID, `#`, and the occurrence's
     * start — `20261110` for a whole day, `20261110T190000Z` in UTC otherwise.
     */
    public function key(): string
    {
        return self::keyOf($this->uid, $this->recurrenceId, $this->allDay);
    }

    public static function keyOf(string $uid, ?DateTimeImmutable $recurrenceId, bool $isDate): string
    {
        if (!$recurrenceId instanceof DateTimeImmutable) {
            return $uid;
        }

        return $uid.'#'.($isDate ? $recurrenceId->format('Ymd') : $recurrenceId->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'));
    }

    /**
     * The version of the event: what the importer compares from one read to the next.
     */
    public function version(): EventVersion
    {
        $fields = [
            Field::Title->value => $this->title,
            Field::Location->value => $this->location,
            Field::Description->value => $this->description,
            Field::Tags->value => $this->tags,
        ];
        $content = [
            'start' => $this->moment($this->start),
            'end' => $this->moment($this->end),
            'allDay' => $this->allDay,
            'floating' => $this->floating,
            'url' => $this->url?->toString(),
            'status' => $this->status->value,
            'rule' => $this->rule instanceof RecurrenceRule ? (string) $this->rule : null,
            'additions' => array_map($this->moment(...), $this->additions),
            'exclusions' => array_map($this->moment(...), $this->exclusions),
            ...$fields,
        ];

        return new EventVersion(
            $this->sequence,
            $this->lastModified,
            $this->hash($content),
            array_map($this->hash(...), $fields),
        );
    }

    private function moment(DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d\TH:i:sP').' '.$moment->getTimezone()->getName();
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
    }
}
