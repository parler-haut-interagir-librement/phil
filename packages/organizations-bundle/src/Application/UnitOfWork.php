<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Closure;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Runs a change in one transaction of the store and dispatches its events once the transaction is committed: a
 * listener never hears of a change that was rolled back. When the application wraps the call in a transaction of its
 * own on the same connection, the events are dispatched when the bundle's part returns.
 */
final readonly class UnitOfWork
{
    public function __construct(
        private OrganizationStore $store,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @template T
     *
     * @param Closure(EventRecorder): T $work
     *
     * @return T
     */
    public function run(Closure $work): mixed
    {
        $recorder = new EventRecorder();
        $result = $this->store->transactional(static fn (): mixed => $work($recorder));

        foreach ($recorder->events() as $event) {
            $this->events?->dispatch($event);
        }

        return $result;
    }
}
