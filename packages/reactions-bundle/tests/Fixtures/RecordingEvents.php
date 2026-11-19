<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Fixtures;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps what it is given.
 */
final class RecordingEvents implements EventDispatcherInterface
{
    /**
     * @param list<object> $events
     */
    public function __construct(
        private array &$events,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
