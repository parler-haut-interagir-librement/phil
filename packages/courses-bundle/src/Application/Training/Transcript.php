<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateTimeInterface;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * A learner's transcript: the courses they completed, the latest first, with the credits each granted, and the total
 * of the credits by unit, in hundredths.
 */
final readonly class Transcript
{
    /** The columns of the CSV export, in their order. */
    public const array COLUMNS = ['course', 'title', 'completed_at', 'credits', 'unit', 'assignment', 'cycle'];

    /**
     * @param list<TranscriptEntry> $entries
     */
    public function __construct(
        public LearnerId $learner,
        public array $entries,
    ) {
    }

    /**
     * The hundredths of credits of that unit the transcript adds up to.
     */
    public function total(CreditUnit $unit): int
    {
        $hundredths = 0;

        foreach ($this->entries as $entry) {
            if ($unit === $entry->credits?->unit) {
                $hundredths += $entry->credits->hundredths;
            }
        }

        return $hundredths;
    }

    /**
     * @return array{learner: string, entries: list<array{course: string, title: string, completed_at: string, credits: ?string, unit: ?string, assignment: ?string, cycle: ?int}>, totals: array<string, string>}
     */
    public function toArray(): array
    {
        $totals = [];

        foreach (CreditUnit::cases() as $unit) {
            $total = $this->total($unit);

            if (0 !== $total) {
                $totals[$unit->value] = Credits::write($total);
            }
        }

        return [
            'learner' => $this->learner->value,
            'entries' => array_map(static fn (TranscriptEntry $entry): array => [
                'course' => $entry->course->value,
                'title' => $entry->courseTitle,
                'completed_at' => $entry->completedAt->format(DateTimeInterface::ATOM),
                'credits' => $entry->credits?->amount(),
                'unit' => $entry->credits?->unit->value,
                'assignment' => $entry->assignment?->value,
                'cycle' => $entry->cycle,
            ], $this->entries),
            'totals' => $totals,
        ];
    }

    /**
     * The transcript as CSV, RFC 4180: a header line, then a line by entry. A cell that a spreadsheet would read as a
     * formula — starting with `=`, `+`, `-`, `@`, a tab or a carriage return — is prefixed with a quote.
     */
    public function csv(): string
    {
        $lines = [$this->line(self::COLUMNS)];

        foreach ($this->toArray()['entries'] as $entry) {
            $lines[] = $this->line(array_map(static fn (int|string|null $cell): string => (string) $cell, $entry));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param array<string> $cells
     */
    private function line(array $cells): string
    {
        return implode(',', array_map(static function (string $cell): string {
            if (1 === preg_match('/^[=+\-@\t\r]/', $cell)) {
                $cell = "'".$cell;
            }

            return 1 === preg_match('/[",\r\n]/', $cell) ? '"'.str_replace('"', '""', $cell).'"' : $cell;
        }, $cells));
    }
}
