<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Phil\DocumentImportBundle\Application\Contract\PendingImports;
use Phil\DocumentImportBundle\Application\PendingImport;

/**
 * Pending imports in memory, their claims numbered, every claim released kept in order.
 */
final class MemoryPendingImports implements PendingImports
{
    /** @var array<string, PendingImport> */
    public array $imports = [];

    /** @var list<string> */
    public array $released = [];

    private int $count = 0;

    #[Override]
    public function keep(PendingImport $import): string
    {
        $claim = 'claim-'.++$this->count;
        $this->imports[$claim] = $import;

        return $claim;
    }

    #[Override]
    public function take(string $claim): ?PendingImport
    {
        return $this->imports[$claim] ?? null;
    }

    #[Override]
    public function release(string $claim): void
    {
        $this->released[] = $claim;
        unset($this->imports[$claim]);
    }
}
