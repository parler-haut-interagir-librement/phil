<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;

/**
 * A value within an interval: `{"<": [0, {"var": "age"}, 18]}` excludes its bounds, `{"<=": […]}` includes them.
 */
final readonly class Between implements Expression
{
    public function __construct(public Expression $low, public Expression $value, public Expression $high, public bool $inclusive = false)
    {
    }

    #[Override]
    public function jsonLogic(): array
    {
        return [$this->inclusive ? '<=' : '<' => [$this->low->jsonLogic(), $this->value->jsonLogic(), $this->high->jsonLogic()]];
    }

    #[Override]
    public function children(): array
    {
        return [$this->low, $this->value, $this->high];
    }
}
