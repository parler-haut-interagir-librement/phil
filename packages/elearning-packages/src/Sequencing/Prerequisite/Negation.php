<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing\Prerequisite;

use Closure;
use Override;

/**
 * `~ expression`: met when the expression is not.
 *
 * @internal built by `Prerequisites::parse()`; not part of the public API
 */
final readonly class Negation extends Node
{
    public function __construct(
        private Node $node,
    ) {
    }

    #[Override]
    public function isMet(Closure $status): bool
    {
        return !$this->node->isMet($status);
    }

    #[Override]
    public function identifiers(): array
    {
        return $this->node->identifiers();
    }
}
