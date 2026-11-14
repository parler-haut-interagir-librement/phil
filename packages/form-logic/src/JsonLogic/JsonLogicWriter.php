<?php

declare(strict_types=1);

namespace Phil\FormLogic\JsonLogic;

use Phil\FormLogic\Contract\Expression;

/**
 * Writes an expression as a JsonLogic rule, the canonical form the reader reads back to an equal expression.
 */
final readonly class JsonLogicWriter
{
    public function write(Expression $expression): string
    {
        return json_encode($expression->jsonLogic(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
