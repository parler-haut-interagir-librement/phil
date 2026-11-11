<?php

declare(strict_types=1);

namespace Phil\DddBundle\Domain\Event;

use DateTimeImmutable;

/**
 * A past fact, inside a context.
 *
 * This is **not** an integration event: what crosses a context boundary goes through the `Contracts` of the
 * emitting context and through an anti-corruption layer on the consumer side.
 *
 * There is no event sourcing: events notify, they do not reconstitute. State is stored as state.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
