<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;

/**
 * Membership: `{"in": [{"var": "team"}, ["Blue", "Green"]]}` — a list holds an item strictly equal to the needle —, or
 * `{"in": ["@", {"var": "contact"}]}` — a text holds the needle written as text.
 */
final readonly class In implements Expression
{
    public function __construct(public Expression $needle, public Expression $haystack)
    {
    }

    #[Override]
    public function jsonLogic(): array
    {
        return ['in' => [$this->needle->jsonLogic(), $this->haystack->jsonLogic()]];
    }

    #[Override]
    public function children(): array
    {
        return [$this->needle, $this->haystack];
    }
}
