<?php

declare(strict_types=1);

namespace Phil\Enums\Exceptions;

use Error;

/**
 * An enum was called statically by a name none of its cases has (`Status::ARCHIVED()` on an enum without
 * `ARCHIVED`). It is an `Error`, worded as PHP words an undefined class constant, since `Status::ARCHIVED` without
 * the parentheses would have failed the same way.
 */
class UndefinedCaseError extends Error
{
    public function __construct(string $enum, string $case)
    {
        parent::__construct(\sprintf('Undefined constant %s::%s', $enum, $case));
    }
}
