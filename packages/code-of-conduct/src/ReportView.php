<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * A report opened for a responsible person who may handle it: the report, what happened, and its notes.
 *
 * Its texts are never dumped: `var_dump()` and the debuggers show `[sensitive]`.
 */
final readonly class ReportView
{
    /**
     * @param list<NoteView> $notes oldest first
     */
    public function __construct(
        public Report $report,
        public string $text,
        public array $notes,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['report' => $this->report->id, 'text' => '[sensitive]', 'notes' => \count($this->notes)];
    }
}
