<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Event;

/**
 * A version of a document was published, by its identifiers: the type, as `DocumentType` values it, and the number.
 */
final readonly class DocumentPublished
{
    public function __construct(
        public string $type,
        public int $version,
    ) {
    }
}
