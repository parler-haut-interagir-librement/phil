<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

use DateTimeImmutable;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;

/**
 * The dry run of a file, the only thing an import writes: the rows to create and to update, in the order they are
 * written — a row after the rows of the file it refers to —, the rows refused with their reasons, the mapping used,
 * and a fingerprint that changes when any of these does.
 */
final readonly class ImportPlan
{
    public string $fingerprint;

    /**
     * @internal built by `ImportPlanner`
     *
     * @param list<string>       $headers
     * @param list<PlannedRow>   $rows       in the order they are written
     * @param list<RowRejection> $rejections in the order of the file
     */
    public function __construct(
        public ImportProfile $profile,
        public array $headers,
        public ColumnMapping $mapping,
        public array $rows,
        public array $rejections,
    ) {
        $this->fingerprint = hash('sha256', json_encode([
            $profile->withMapping($mapping)->toArray(),
            $headers,
            array_map(static fn (PlannedRow $row): array => [$row->line, $row->action->value, array_map(self::text(...), $row->values)], $rows),
            array_map(static fn (RowRejection $rejection): array => [$rejection->line, array_map(static fn (CellError $error): array => [$error->column, $error->reason->value, $error->relatedLine], $rejection->errors)], $rejections),
        ], \JSON_THROW_ON_ERROR));
    }

    public function creates(): int
    {
        return \count(array_filter($this->rows, static fn (PlannedRow $row): bool => RowAction::Create === $row->action));
    }

    public function updates(): int
    {
        return \count($this->rows) - $this->creates();
    }

    public function refusals(): int
    {
        return \count($this->rejections);
    }

    /**
     * Every row of the file that is not blank, in the order of the file, with what the import will do with it.
     *
     * @return list<PreviewLine>
     */
    public function preview(): array
    {
        $lines = [];

        foreach ($this->rows as $row) {
            $lines[$row->line] = new PreviewLine($row->line, $row->action);
        }

        foreach ($this->rejections as $rejection) {
            $lines[$rejection->line] = new PreviewLine($rejection->line, RowAction::Refuse, $rejection->errors);
        }

        ksort($lines);

        return array_values($lines);
    }

    /**
     * The English sentence of an error, with the header of the file its column is read from.
     */
    public function describe(CellError $error): string
    {
        return $error->message(null === $error->column ? null : $this->mapping->headerOf($error->column) ?? $error->column);
    }

    private static function text(string|int|bool|DateTimeImmutable|null $value): ?string
    {
        return null === $value ? null : ColumnType::text($value);
    }
}
