<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order.
 */
final class RecordingEvents implements EventDispatcherInterface
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
