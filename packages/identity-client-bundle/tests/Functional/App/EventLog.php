<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional\App;

/**
 * The events of the bundle a test can assert on.
 */
final class EventLog
{
    /** @var list<object> */
    public array $events = [];

    public function __invoke(object $event): void
    {
        $this->events[] = $event;
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
