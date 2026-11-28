<?php

declare(strict_types=1);

namespace Phil\Rules\Condition;

/**
 * Holds when at least one of its conditions holds. Evaluated in order, it stops at the first that holds.
 */
final readonly class Any implements Condition
{
    /** @var list<Condition> */
    public array $conditions;

    public function __construct(Condition ...$conditions)
    {
        $this->conditions = array_values($conditions);
    }
}
