<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use LogicException;

/**
 * A table declared in a way the bundle cannot draw: the mistake is the developer's, and it shows at the first render.
 */
final class InvalidDefinition extends LogicException implements DataTableException
{
    public static function because(string $table, string $reason): self
    {
        return new self(\sprintf('The table "%s" is invalid: %s', $table, $reason));
    }
}
