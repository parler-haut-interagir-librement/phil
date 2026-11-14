<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps each event.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}
