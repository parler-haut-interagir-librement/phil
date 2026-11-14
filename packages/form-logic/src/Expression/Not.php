<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;

/**
 * JsonLogic's `!`: whether the operand is false, `{"!": [{"var": "agreed"}]}`; with `$double`, `!!`, whether it is true.
 */
final readonly class Not implements Expression
{
    public function __construct(public Expression $operand, public bool $double = false)
    {
    }

    #[Override]
    public function jsonLogic(): array
    {
        return [$this->double ? '!!' : '!' => [$this->operand->jsonLogic()]];
    }

    #[Override]
    public function children(): array
    {
        return [$this->operand];
    }
}
