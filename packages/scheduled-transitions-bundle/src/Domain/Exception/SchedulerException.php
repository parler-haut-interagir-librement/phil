<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface SchedulerException extends Throwable
{
}
