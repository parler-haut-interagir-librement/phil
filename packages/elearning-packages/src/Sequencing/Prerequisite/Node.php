<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing\Prerequisite;

use Closure;

/**
 * A part of a prerequisites expression.
 *
 * @internal built by `Prerequisites::parse()`; not part of the public API
 */
abstract readonly class Node
{
    /**
     * @param Closure(string): string $status the `lesson_status` of an item by its identifier
     */
    abstract public function isMet(Closure $status): bool;

    /**
     * @return list<string>
     */
    abstract public function identifiers(): array;
}
