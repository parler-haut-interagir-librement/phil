<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\Status\IncidentState;

/**
 * Something that went wrong, as visitors are told: its title, its impact on the components it names, and its thread
 * of updates, kept newest first. It started with its oldest update and stands where its newest one says; it is over
 * once that one says it is resolved.
 */
final readonly class Incident
{
    public const int MAX_UPDATES = 100;

    /** @var non-empty-string */
    public string $id;

    /** @var non-empty-string */
    public string $title;

    /** @var list<non-empty-string> */
    public array $components;

    /** @var non-empty-list<IncidentUpdate> */
    public array $updates;

    /**
     * @param list<string>         $components
     * @param list<IncidentUpdate> $updates
     */
    public function __construct(
        string $id,
        string $title,
        public ComponentStatus $impact,
        array $components,
        array $updates,
    ) {
        $this->id = Text::id($id, 'incident id');
        $this->title = Text::line($title, 'incident title');

        if (!$impact->isOutage()) {
            throw InvalidStatusRecord::because('The impact of an incident is "degraded", "partial_outage" or "major_outage".');
        }

        $this->components = Text::ids($components, 'component of an incident');

        if ([] === $updates || \count($updates) > self::MAX_UPDATES) {
            throw InvalidStatusRecord::because(\sprintf('An incident has 1 to %d updates.', self::MAX_UPDATES));
        }

        usort($updates, static fn (IncidentUpdate $a, IncidentUpdate $b): int => $b->at <=> $a->at);
        $this->updates = $updates;
    }

    public function state(): IncidentState
    {
        return $this->updates[0]->state;
    }

    public function isResolved(): bool
    {
        return IncidentState::Resolved === $this->updates[0]->state;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->updates[\count($this->updates) - 1]->at;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updates[0]->at;
    }

    /**
     * The moment of the update that resolved it; null while it is not resolved.
     */
    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->isResolved() ? $this->updates[0]->at : null;
    }

    /**
     * Whether it is going on: started by `$now`, not resolved.
     */
    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return !$this->isResolved() && $this->updates[\count($this->updates) - 1]->at <= $now;
    }

    public function affects(string $component): bool
    {
        return \in_array($component, $this->components, true);
    }
}
