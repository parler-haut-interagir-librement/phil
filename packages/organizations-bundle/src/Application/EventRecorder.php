<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

/**
 * The events of one unit of work, held until it is committed. A new recorder serves each unit of work.
 */
final class EventRecorder
{
    /** @var list<object> */
    private array $events = [];

    public function record(object ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /**
     * @return list<object> the events recorded, in order
     */
    public function events(): array
    {
        return $this->events;
    }
}
