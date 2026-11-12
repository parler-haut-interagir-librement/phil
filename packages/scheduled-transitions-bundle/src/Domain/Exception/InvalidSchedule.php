<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Exception;

use InvalidArgumentException;

final class InvalidSchedule extends InvalidArgumentException implements SchedulerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
