<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps the events it is given, and hands each to the listeners it was given.
 */
final class RecordingEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /** @var list<callable(object): void> */
    public array $listeners = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        foreach ($this->listeners as $listener) {
            $listener($event);
        }

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
