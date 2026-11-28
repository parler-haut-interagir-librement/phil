<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarImport\Override\EditorOverride;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Url;

/**
 * An imported event ready to become an event of the application's calendar: the source's fields, the editor's in
 * place of those replaced, and the marks of where it comes from — the source's identifier, its key and its UID there.
 * It belongs to no calendar library: an adapter of the application turns it into its own events.
 */
final readonly class ImportedDraft
{
    /**
     * @param list<string>            $tags
     * @param list<DateTimeImmutable> $additions
     * @param list<DateTimeImmutable> $exclusions
     * @param list<Field>             $overridden the fields the editor replaced
     */
    public function __construct(
        public string $sourceId,
        public string $key,
        public string $uid,
        public ?DateTimeImmutable $recurrenceId,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay,
        public bool $floating,
        public DateTimeZone $timezone,
        public ?string $title,
        public ?string $description,
        public ?string $location,
        public ?Url $url,
        public EventStatus $status,
        public array $tags,
        public ?RecurrenceRule $rule,
        public array $additions,
        public array $exclusions,
        public bool $hidden,
        public array $overridden = [],
    ) {
    }

    /**
     * The draft of an event read from a source, the editor's override applied.
     */
    public static function of(string $sourceId, SourceEvent $event, ?EditorOverride $override = null): self
    {
        return new self(
            $sourceId,
            $event->key(),
            $event->uid,
            $event->recurrenceId,
            $event->start,
            $event->end,
            $event->allDay,
            $event->floating,
            $event->timezone,
            $override->title ?? $event->title,
            $override->description ?? $event->description,
            $override->location ?? $event->location,
            $event->url,
            $event->status,
            $override->tags ?? $event->tags,
            $event->rule,
            $event->additions,
            $event->exclusions,
            $override->hidden ?? false,
            $override?->fields() ?? [],
        );
    }

    public function isCancelled(): bool
    {
        return EventStatus::Cancelled === $this->status;
    }
}
