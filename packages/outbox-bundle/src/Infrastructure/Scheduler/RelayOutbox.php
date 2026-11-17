<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Scheduler;

/**
 * The message the schedule `phil_outbox` sends at each tick: it carries nothing, the records stay in the outbox.
 */
final readonly class RelayOutbox
{
}
