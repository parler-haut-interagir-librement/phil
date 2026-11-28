<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use DomainException;

/**
 * A target or an action the configuration does not declare. A request names them; it never names a class.
 */
final class UnknownAction extends DomainException implements BulkActionsException
{
    public static function target(string $target): self
    {
        return new self(\sprintf('No bulk target is named "%s".', $target));
    }

    public static function action(string $target, string $action): self
    {
        return new self(\sprintf('The bulk target "%s" has no action named "%s".', $target, $action));
    }
}
