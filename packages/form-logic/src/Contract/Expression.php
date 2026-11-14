<?php

declare(strict_types=1);

namespace Phil\FormLogic\Contract;

/**
 * A node of a logic expression: immutable, built valid, and written as one rule of the JsonLogic subset.
 *
 * The library's own nodes, under `Phil\FormLogic\Expression`, are the whole language: the evaluators of PHP and of the
 * Stimulus controller know each of them, and nothing else.
 *
 * @phpstan-type JsonLogic array<array-key, mixed>|string|int|float|bool|null
 */
interface Expression
{
    /**
     * The rule as JsonLogic, ready for `json_encode()`.
     *
     * @return array<array-key, mixed>|string|int|float|bool|null
     */
    public function jsonLogic(): array|string|int|float|bool|null;

    /**
     * The nodes directly under this one, in the order they are written.
     *
     * @return list<Expression>
     */
    public function children(): array;
}
