<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps the events dispatched, in order.
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

    /**
     * Forgets the events dispatched so far.
     */
    public function clear(): void
    {
        $this->events = [];
    }
}
