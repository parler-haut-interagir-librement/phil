<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Outbox;

/**
 * A document of an entity a flush deleted, to remove once the outbox relays it: the index and the document's
 * identifier there, read before the row was deleted.
 */
final readonly class RemoveDocument
{
    public const string TYPE = 'phil_search.remove_document';

    public function __construct(
        public string $index,
        public string $document,
    ) {
    }
}
