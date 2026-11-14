<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Closure;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class SpyDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /**
     * What a subscriber throws, after the event is recorded.
     */
    public ?Throwable $failure = null;

    /**
     * What a subscriber does, after the event is recorded.
     */
    public ?Closure $listener = null;

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        if ($this->listener instanceof Closure) {
            ($this->listener)($event);
        }

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $event;
    }
}
