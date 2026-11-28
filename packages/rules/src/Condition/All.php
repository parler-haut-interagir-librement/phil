<?php

declare(strict_types=1);

namespace Phil\Rules\Condition;

/**
 * Holds when every one of its conditions holds. Evaluated in order, it stops at the first that fails.
 */
final readonly class All implements Condition
{
    /** @var list<Condition> */
    public array $conditions;

    public function __construct(Condition ...$conditions)
    {
        $this->conditions = array_values($conditions);
    }
}
