<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * A dispatcher that keeps the events it is given, in order, or fails as a listener would once told to.
 */
final class RecordedEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function __construct(
        private readonly bool $fail = false,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        if ($this->fail) {
            throw new RuntimeException('The listener failed.');
        }

        $this->events[] = $event;

        return $event;
    }
}
