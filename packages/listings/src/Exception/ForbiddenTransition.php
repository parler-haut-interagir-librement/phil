<?php

declare(strict_types=1);

namespace Phil\Listings\Exception;

use LogicException;

/**
 * A listing or a response was asked for a change its status does not allow: approving a published listing,
 * accepting a declined response.
 */
final class ForbiddenTransition extends LogicException implements ListingsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
