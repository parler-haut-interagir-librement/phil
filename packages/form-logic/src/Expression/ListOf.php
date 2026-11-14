<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;

/**
 * A list whose items are expressions, such as the choices an `in` looks among: `["Blue", "Green"]`.
 */
final readonly class ListOf implements Expression
{
    /**
     * @param list<Expression> $items
     */
    public function __construct(public array $items)
    {
    }

    #[Override]
    public function jsonLogic(): array
    {
        return array_map(static fn (Expression $item): array|string|int|float|bool|null => $item->jsonLogic(), $this->items);
    }

    #[Override]
    public function children(): array
    {
        return $this->items;
    }
}
