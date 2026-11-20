<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing\Prerequisite;

use Closure;
use Override;

/**
 * At least a count of the expressions are met: `a & b` needs all of them, `a | b` one, `2*{a,b,c}` two.
 *
 * @internal built by `Prerequisites::parse()`; not part of the public API
 */
final readonly class AtLeast extends Node
{
    /**
     * @param non-empty-list<Node> $nodes
     */
    public function __construct(
        private int $count,
        private array $nodes,
    ) {
    }

    #[Override]
    public function isMet(Closure $status): bool
    {
        $met = 0;

        foreach ($this->nodes as $node) {
            if ($node->isMet($status) && ++$met >= $this->count) {
                return true;
            }
        }

        return $this->count <= 0;
    }

    #[Override]
    public function identifiers(): array
    {
        return array_merge(...array_map(static fn (Node $node): array => $node->identifiers(), $this->nodes));
    }
}
