<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

final class InMemoryDocumentSeries implements DocumentSeries
{
    /** @var array<string, int> */
    private array $last = [];

    #[Override]
    public function nextIn(DocumentType $type): string
    {
        $this->last[$type->code] = ($this->last[$type->code] ?? 0) + 1;

        return $type->format($this->last[$type->code]);
    }
}
