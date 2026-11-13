<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order.
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
