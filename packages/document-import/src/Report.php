<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Phil\Ujo\Block;

/**
 * What an import left out, one `Omission` per kind and detail, the kinds in the order they were first met and the
 * details of each kind after it; as rows, as sentences, or as a block rendered with the document.
 */
final class Report
{
    /** @var array<string, array<string, Omission>> */
    private array $omissions = [];

    public function add(Omitted $what, string $detail = ''): void
    {
        $this->omissions[$what->value][$detail] = new Omission($what, $detail, ($this->omissions[$what->value][$detail]->count ?? 0) + 1);
    }

    /**
     * @return list<Omission>
     */
    public function omissions(): array
    {
        $omissions = [];

        foreach ($this->omissions as $details) {
            foreach ($details as $omission) {
                $omissions[] = $omission;
            }
        }

        return $omissions;
    }

    public function isEmpty(): bool
    {
        return [] === $this->omissions;
    }

    /**
     * How many times things were left out, of one kind or of all.
     */
    public function count(?Omitted $what = null): int
    {
        $count = 0;

        foreach ($this->omissions() as $omission) {
            if (!$what instanceof Omitted || $what === $omission->what) {
                $count += $omission->count;
            }
        }

        return $count;
    }

    /**
     * The report as sentences a person reads, one per kind: its description, then each detail with how many times it
     * was met, or the count alone.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        $lines = [];

        foreach ($this->omissions as $what => $details) {
            $parts = [];

            foreach ($details as $detail => $omission) {
                if ('' !== $detail) {
                    $parts[] = $detail.' ('.$omission->count.')';
                }
            }

            // Without a detail, the kind holds one omission, under the empty detail.
            $description = rtrim(Omitted::from($what)->description(), '.');
            $lines[] = $description.([] === $parts ? ' ('.$details['']->count.').' : ': '.implode(', ', $parts).'.');
        }

        return $lines;
    }

    /**
     * The report as a `warning` block — its title, then its sentences one per line — to render with the document.
     */
    public function toBlock(string $title = 'What was not imported', string $id = 'import-report'): Block
    {
        return new Block($id, 'warning', [
            'title' => htmlspecialchars($title, \ENT_QUOTES),
            'message' => implode('<br>', array_map(static fn (string $line): string => htmlspecialchars($line, \ENT_QUOTES), $this->summary())),
        ]);
    }

    /**
     * The report as JSON-ready rows: `what`, `detail`, `count`, `description`.
     *
     * @return list<array{what: string, detail: string, count: int, description: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Omission $omission): array => [
            'what' => $omission->what->value,
            'detail' => $omission->detail,
            'count' => $omission->count,
            'description' => $omission->what->description(),
        ], $this->omissions());
    }
}
