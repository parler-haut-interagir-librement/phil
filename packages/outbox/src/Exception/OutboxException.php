<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use Throwable;

/**
 * What every exception of the outbox is, to catch them all at once.
 */
interface OutboxException extends Throwable
{
}
