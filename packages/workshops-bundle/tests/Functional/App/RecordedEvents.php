<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Functional\App;

/**
 * The events of the workshops the application heard, in order.
 */
final class RecordedEvents
{
    /** @var list<object> */
    public array $events = [];

    public function __invoke(object $event): void
    {
        $this->events[] = $event;
    }
}
