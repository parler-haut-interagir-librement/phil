<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Functional\App;

use Override;
use Phil\SvgBlocksBundle\Application\Contract\MarkupDocuments;
use Phil\Ujo\Document;

/**
 * The application's documents, in memory, by subject.
 */
final class InMemoryDocuments implements MarkupDocuments
{
    /** @var array<string, Document> */
    public array $documents = [];

    /** @var list<string> */
    public array $saved = [];

    #[Override]
    public function find(string $subject): ?Document
    {
        return $this->documents[$subject] ?? null;
    }

    #[Override]
    public function save(string $subject, Document $document): void
    {
        $this->documents[$subject] = $document;
        $this->saved[] = $subject;
    }
}
