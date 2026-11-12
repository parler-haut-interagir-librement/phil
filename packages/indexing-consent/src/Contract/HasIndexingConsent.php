<?php

declare(strict_types=1);

namespace Phil\IndexingConsent\Contract;

use Phil\IndexingConsent\Indexing;

/**
 * A public record whose reach the person it is about decides: a profile, a listing in a directory, a post signed with a
 * name. The record keeps the choice; the readers — the sitemap, the structured data, the directory — ask it.
 */
interface HasIndexingConsent
{
    public function indexing(): Indexing;
}
