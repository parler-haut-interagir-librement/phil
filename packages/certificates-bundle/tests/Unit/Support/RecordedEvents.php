<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps what it is given, in order.
 */
final class RecordedEvents implements EventDispatcherInterface
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
