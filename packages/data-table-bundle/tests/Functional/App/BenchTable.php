<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Functional\App;

use DateTimeImmutable;
use Override;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Tests\Fixtures\Book;
use Phil\DataTableBundle\Tests\Fixtures\Status;

/**
 * The table of the benchmark: 100 rows of 10 columns.
 */
final readonly class BenchTable implements DataTable
{
    #[Override]
    public static function name(): string
    {
        return 'bench';
    }

    #[Override]
    public function configure(TableBuilder $table): void
    {
        $table->caption('Benchmark')->column(Column::text('title', 'Title')->sortable())->pageSizes(100);

        foreach (['a', 'b', 'c'] as $name) {
            $table->column(Column::number('pages'.$name, 'Pages')->of('pages'));
            $table->column(Column::date('published'.$name, 'Published')->of('published'));
            $table->column(Column::enum('status'.$name, 'Status', Status::class)->of('status'));
        }
    }

    #[Override]
    public function source(array $context): DataSource
    {
        $rows = [];

        for ($id = 1; $id <= 100; ++$id) {
            $rows[] = new Book($id, 'Book '.$id, 100 + $id, new DateTimeImmutable('2026-01-02'), Status::Live);
        }

        return new InMemoryDataSource($rows);
    }
}
