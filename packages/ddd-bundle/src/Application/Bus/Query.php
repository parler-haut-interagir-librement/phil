<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Bus;

/**
 * A question asked of the read model.
 *
 * Queries read **DTOs through DBAL/SQL and bypass the aggregates entirely**: an aggregate is loaded to enforce
 * an invariant, and a read enforces none.
 *
 * @template TResult
 */
interface Query
{
}
