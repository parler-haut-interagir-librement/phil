<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit\Fixture;

use Closure;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A PSR-14 dispatcher that keeps the events it receives, and runs a listener of the test before keeping each.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /**
     * @param Closure(object): void|null $listener
     */
    public function __construct(
        public ?Closure $listener = null,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        if ($this->listener instanceof Closure) {
            ($this->listener)($event);
        }

        $this->events[] = $event;

        return $event;
    }
}
