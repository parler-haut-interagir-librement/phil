<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Exception;

use LogicException;

/**
 * A suggestion accepted or rejected a second time, or the decision on a task that proposed nothing.
 */
final class AlreadyDecided extends LogicException implements AiException
{
    public static function suggestion(string $id, string $decision): self
    {
        return new self(\sprintf('The suggestion "%s" is already %s.', $id, $decision));
    }

    public static function failedTask(string $id): self
    {
        return new self(\sprintf('The task "%s" failed and proposed nothing to decide on.', $id));
    }
}
