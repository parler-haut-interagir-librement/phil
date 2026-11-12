<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a flow name, a setting of the rule, or a history of the wrong length.
 */
final class InvalidFlowValue extends InvalidArgumentException implements BrokenFlowAlertsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
