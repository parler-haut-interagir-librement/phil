<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An event dispatcher that keeps what it is given.
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
}
