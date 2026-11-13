<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An event dispatcher that keeps what it is given.
 */
final class Events implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return $event;
    }
}
