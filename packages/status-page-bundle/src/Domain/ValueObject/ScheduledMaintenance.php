<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;

/**
 * A planned window of work on some components: its title, an optional description, and when it starts and ends. It
 * is upcoming before it starts, in progress from its start until its end, over from its end.
 */
final readonly class ScheduledMaintenance
{
    /** @var non-empty-string */
    public string $id;

    /** @var non-empty-string */
    public string $title;

    public ?string $description;

    /** @var list<non-empty-string> */
    public array $components;

    /**
     * @param list<string> $components
     */
    public function __construct(
        string $id,
        string $title,
        array $components,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        ?string $description = null,
    ) {
        $this->id = Text::id($id, 'maintenance id');
        $this->title = Text::line($title, 'maintenance title');
        $this->description = null === $description ? null : Text::message($description, 'maintenance description');
        $this->components = Text::ids($components, 'component of a maintenance');

        if ($endsAt <= $startsAt) {
            throw InvalidStatusRecord::because('A maintenance ends after it starts.');
        }
    }

    public function isUpcomingAt(DateTimeImmutable $now): bool
    {
        return $now < $this->startsAt;
    }

    public function isInProgressAt(DateTimeImmutable $now): bool
    {
        return $this->startsAt <= $now && $now < $this->endsAt;
    }

    public function affects(string $component): bool
    {
        return \in_array($component, $this->components, true);
    }
}
