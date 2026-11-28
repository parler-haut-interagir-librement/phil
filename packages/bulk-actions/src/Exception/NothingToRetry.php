<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use LogicException;

/**
 * A retry asked of a run that is still working, or whose items all succeeded or were refused for good.
 */
final class NothingToRetry extends LogicException implements BulkActionsException
{
    public static function stillRunning(string $id): self
    {
        return new self(\sprintf('The bulk run "%s" is still running.', $id));
    }

    public static function noFailure(string $id): self
    {
        return new self(\sprintf('The bulk run "%s" has no item to retry.', $id));
    }
}
