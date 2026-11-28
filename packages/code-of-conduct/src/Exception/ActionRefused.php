<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Exception;

use RuntimeException;

/**
 * An action the state refuses: accepting a version that is no longer the current one, a step a report cannot take
 * from where it is, a report nobody could receive.
 */
final class ActionRefused extends RuntimeException implements CodeOfConductException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
