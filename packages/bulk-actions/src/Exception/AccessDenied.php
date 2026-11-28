<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use RuntimeException;

/**
 * The person may not run the action on the target, at the selection, the preview, the confirmation or a retry.
 */
final class AccessDenied extends RuntimeException implements BulkActionsException
{
    public static function toRun(string $target, string $action): self
    {
        return new self(\sprintf('The bulk action "%s" on "%s" is not granted.', $action, $target));
    }
}
