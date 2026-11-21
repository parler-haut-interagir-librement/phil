<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Support;

use Closure;
use Override;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps every event dispatched, in order, and runs a listener on each when one is given.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /**
     * @param (Closure(object): void)|null $listener
     */
    public function __construct(
        private readonly ?Closure $listener = null,
    ) {
    }

    #[Override]
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        if ($this->listener instanceof Closure) {
            ($this->listener)($event);
        }

        return $event;
    }
}
