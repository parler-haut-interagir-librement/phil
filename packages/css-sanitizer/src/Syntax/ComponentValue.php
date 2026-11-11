<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * A component value of CSS Syntax Level 3: a preserved `Token`, a `SimpleBlock` or a `FunctionValue`.
 *
 * @phpstan-sealed Token|SimpleBlock|FunctionValue
 */
interface ComponentValue
{
    /** The line of the source on which the value starts, from 1. */
    public function line(): int;
}
