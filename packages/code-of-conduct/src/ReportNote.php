<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;

/**
 * A note a responsible person wrote on a report, sealed: what they learned, did or decided.
 */
final readonly class ReportNote
{
    public function __construct(
        public string $id,
        public string $report,
        public string $author,
        public string $sealedText,
        public DateTimeImmutable $writtenAt,
    ) {
        Identifier::check($id, 'A note');
        Identifier::check($report, 'A report');
        Identifier::check($author, 'An author');
    }
}
