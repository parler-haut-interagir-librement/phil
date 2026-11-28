<?php

declare(strict_types=1);

namespace Phil\Rules\Condition;

/**
 * Holds when its condition fails.
 */
final readonly class Not implements Condition
{
    public function __construct(public Condition $condition)
    {
    }
}
