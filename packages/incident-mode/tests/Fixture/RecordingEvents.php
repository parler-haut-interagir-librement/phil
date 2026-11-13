<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps every event dispatched, in order.
 */
final class RecordingEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
