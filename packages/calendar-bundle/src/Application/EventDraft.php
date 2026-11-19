<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\Recurrence\RecurrenceRule;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An event as a form fills it: what `Calendar` schedules or applies to an event.
 */
final class EventDraft
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    public ?string $description = null;

    #[Assert\Length(max: 255)]
    public ?string $location = null;

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 2048)]
    public ?string $url = null;

    public ?Venue $venue = null;

    #[Assert\Length(max: 2048)]
    #[Assert\AtLeastOneOf([new Assert\Url(requireTld: true), new Assert\Regex('#^/(?![/\\\\])[^\\\\]*$#D')], message: 'calendar.image', includeInternalMessages: false)]
    public ?string $image = null;

    #[Assert\NotNull]
    public ?DateTimeImmutable $start = null;

    #[Assert\NotNull]
    public ?DateTimeImmutable $end = null;

    public bool $allDay = false;

    #[Assert\NotBlank]
    #[Assert\Timezone]
    public ?string $timezone = null;

    public ?RecurrenceRule $rule = null;

    /** @var list<string> */
    public array $tags = [];

    /** How many people can attend each occurrence; none for no limit. */
    #[Assert\Range(min: 1, max: 1000000)]
    public ?int $capacity = null;

    public static function of(Event $event): self
    {
        $draft = new self();
        $draft->title = $event->title();
        $draft->description = $event->description();
        $draft->location = $event->location();
        $draft->url = $event->url();
        $draft->venue = $event->venue();
        $draft->image = $event->image();
        $draft->start = $event->start();
        $draft->end = $event->end();
        $draft->allDay = $event->isAllDay();
        $draft->timezone = $event->timezone()->getName();
        $draft->rule = $event->rule();
        $draft->tags = $event->tags();
        $draft->capacity = $event->capacity();

        return $draft;
    }
}
