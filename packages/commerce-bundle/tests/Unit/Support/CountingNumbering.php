<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;

/**
 * A series per kind in memory: `receipt-1`, `credit_note-1`….
 */
final class CountingNumbering implements DocumentNumbering
{
    /** @var array<string, int> */
    public array $last = [];

    #[Override]
    public function next(DocumentKind $kind): string
    {
        $this->last[$kind->value] = ($this->last[$kind->value] ?? 0) + 1;

        return $kind->value.'-'.$this->last[$kind->value];
    }
}
