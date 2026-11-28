<?php

declare(strict_types=1);

namespace Phil\Rules\Condition;

/**
 * A node of a rule: `All`, `Any` and `Not` compose other conditions, a `Leaf` names a condition type registered by a
 * package. The library evaluates these four classes only; any other implementation is refused when a rule is built.
 */
interface Condition
{
}
