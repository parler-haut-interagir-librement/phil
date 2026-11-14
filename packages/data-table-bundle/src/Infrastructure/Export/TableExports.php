<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Export;

use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Definition\Export;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableExport\ExportWriters;
use Phil\DataTableExport\Format;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The links of a table's export, one per format phil/data-table-export writes, when the table offers it and the viewer
 * holds its attribute. A link carries the table's sort, filters and columns, which the export checks again, and its
 * signed context, which a browser cannot change.
 */
final readonly class TableExports
{
    public const string ROUTE = 'phil_data_table_export';

    public function __construct(
        private ExportWriters $writers,
        private UrlGeneratorInterface $urls,
        private TableAccess $access,
    ) {
    }

    public function writers(): ExportWriters
    {
        return $this->writers;
    }

    /**
     * Whether the viewer may export the table.
     */
    public function allows(OpenTable $open): bool
    {
        $export = $open->definition->export;

        return $export instanceof Export && (null === $export->attribute || $this->access->isGranted($export->attribute));
    }

    /**
     * @param array{sort: string, filters: array<array-key, mixed>, columns: array<array-key, mixed>} $state
     *
     * @return list<array{format: string, href: string}>
     */
    public function links(OpenTable $open, array $state, string $context, string $seal): array
    {
        if (!$this->allows($open)) {
            return [];
        }

        $query = array_filter([...$state, 'context' => $context, 'seal' => $seal], static fn (mixed $value): bool => '' !== $value && [] !== $value);

        return array_map(fn (Format $format): array => [
            'format' => $format->value,
            'href' => $this->urls->generate(self::ROUTE, ['table' => $open->definition->name, 'format' => $format->value, ...$query]),
        ], $this->writers->formats());
    }
}
