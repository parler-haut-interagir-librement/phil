<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\Csv;

use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * The rows of the log as CSV, one line per row, written to a stream as they are read. A cell a spreadsheet would take
 * for a formula — it starts with `=`, `+`, `-`, `@`, a tab, a carriage return or a line feed — is written after a `'`,
 * so that opening the file never runs what a row holds. A stream that stops taking lines — a client gone — stops the
 * writing.
 */
final class AuditCsv
{
    /** The columns, in order. */
    public const array HEADER = ['recorded_at', 'segment', 'sequence', 'kind', 'action', 'actor', 'subject_type', 'subject_id', 'tenant', 'serialization_version', 'payload', 'previous_hash', 'hash', 'intact'];

    private function __construct()
    {
    }

    /**
     * @param resource           $stream
     * @param iterable<AuditRow> $rows
     *
     * @return int the rows written
     */
    public static function write(mixed $stream, iterable $rows, int $limit): int
    {
        if (!self::line($stream, self::HEADER)) {
            return 0;
        }

        $written = 0;

        foreach ($rows as $row) {
            if ($written >= $limit) {
                break;
            }

            $taken = self::line($stream, [
                $row->instant(),
                $row->segment,
                (string) $row->sequence,
                $row->kind->value,
                $row->facts->action ?? '',
                $row->facts->actor ?? '',
                $row->facts->subject->type ?? '',
                $row->facts->subject->id ?? '',
                $row->facts->tenant ?? '',
                $row->version,
                $row->payload,
                $row->previousHash,
                $row->hash,
                $row->isIntact() ? 'yes' : 'no',
            ]);

            if (!$taken) {
                break;
            }

            ++$written;
        }

        return $written;
    }

    /**
     * A cell as it is written: after a `'` when a spreadsheet would read it as a formula.
     */
    public static function cell(string $value): string
    {
        return '' !== $value && str_contains("=+-@\t\r\n", $value[0]) ? "'".$value : $value;
    }

    /**
     * Whether the stream took the line: `fputcsv()` answers `false` on an error, and 0 when the stream took nothing.
     *
     * @param resource     $stream
     * @param list<string> $cells
     */
    private static function line(mixed $stream, array $cells): bool
    {
        return 0 < (int) @fputcsv($stream, array_map(self::cell(...), $cells), ',', '"', '', "\r\n");
    }
}
