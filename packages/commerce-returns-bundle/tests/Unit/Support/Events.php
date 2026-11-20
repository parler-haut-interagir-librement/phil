<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps the events dispatched.
 */
final class Events implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    #[Override]
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->dispatched[] = $event;

        return $event;
    }
}
