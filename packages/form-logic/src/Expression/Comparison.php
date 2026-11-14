<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;

/**
 * Two values compared: `{"==": [{"var": "team"}, "Blue"]}`, `{">=": [{"var": "age"}, 18]}`.
 */
final readonly class Comparison implements Expression
{
    public function __construct(public ComparisonOperator $operator, public Expression $left, public Expression $right)
    {
    }

    #[Override]
    public function jsonLogic(): array
    {
        return [$this->operator->value => [$this->left->jsonLogic(), $this->right->jsonLogic()]];
    }

    #[Override]
    public function children(): array
    {
        return [$this->left, $this->right];
    }
}
