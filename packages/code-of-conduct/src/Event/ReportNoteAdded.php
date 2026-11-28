<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A responsible person added a note to a report. It names the report, the note and the author only.
 */
final readonly class ReportNoteAdded
{
    public function __construct(
        public string $report,
        public string $note,
        public string $author,
    ) {
    }
}
