<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An event dispatcher that keeps each event.
 */
final class RecordingDispatcher implements EventDispatcherInterface
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
