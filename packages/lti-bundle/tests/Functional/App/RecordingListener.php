<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional\App;

use Throwable;

/**
 * Keeps the events of the bundle the test application hears.
 */
final class RecordingListener
{
    /** @var list<object> */
    public array $events = [];

    /**
     * Thrown once, at the next event, as a listener of the application that fails.
     */
    public ?Throwable $failure = null;

    public function __invoke(object $event): void
    {
        $this->events[] = $event;
        $failure = $this->failure;
        $this->failure = null;

        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }
}
