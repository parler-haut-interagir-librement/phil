<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps the events, and passes each to the listeners given.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /** @var list<callable(object): void> */
    public array $listeners = [];

    #[Override]
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        foreach ($this->listeners as $listener) {
            $listener($event);
        }

        return $event;
    }
}
