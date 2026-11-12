<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit\Fixture;

use Override;
use Phil\LegalNotices\Contract\DocumentSource;
use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;

/**
 * Histories given whole, by type; an empty one for a type left out.
 */
final readonly class InMemorySource implements DocumentSource
{
    /**
     * @param array<string, DocumentHistory> $histories by type
     */
    public function __construct(
        private array $histories = [],
    ) {
    }

    #[Override]
    public function history(DocumentType $type): DocumentHistory
    {
        return $this->histories[$type->value] ?? new DocumentHistory($type);
    }
}
