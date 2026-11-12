<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order.
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
