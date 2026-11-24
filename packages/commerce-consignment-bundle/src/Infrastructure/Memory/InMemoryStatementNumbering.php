<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\StatementNumbering;

/**
 * A series counted in memory from one, for tests: never share one between requests.
 */
final class InMemoryStatementNumbering implements StatementNumbering
{
    private int $last = 0;

    public function __construct(
        private readonly string $prefix = 'ST-',
    ) {
    }

    #[Override]
    public function next(): string
    {
        return $this->prefix.str_pad((string) ++$this->last, 8, '0', \STR_PAD_LEFT);
    }
}
