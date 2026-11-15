<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps every event dispatched, in order.
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

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
    }
}
