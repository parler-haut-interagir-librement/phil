<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps every event dispatched, in order.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /**
     * @return list<object>
     */
    public function heard(): array
    {
        return $this->events;
    }

    #[Override]
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}
