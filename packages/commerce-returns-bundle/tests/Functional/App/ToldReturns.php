<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Functional\App;

use Phil\CommerceReturnsBundle\Domain\Event\ReturnStateChanged;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;

/**
 * Listens to the steps of returns, as the stock or a notification would.
 */
final class ToldReturns
{
    /** @var list<ReturnState> */
    public array $states = [];

    public function __invoke(ReturnStateChanged $event): void
    {
        $this->states[] = $event->to;
    }
}
