<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional\App;

/**
 * Keeps the events of the bundle the test application hears.
 */
final class RecordingListener
{
    /** @var list<object> */
    public array $events = [];

    public function __invoke(object $event): void
    {
        $this->events[] = $event;
    }
}
