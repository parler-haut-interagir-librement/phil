<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Functional\App;

use Override;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Exception\ActionRefused;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Tests\Fixtures\Book;
use Phil\DataTableBundle\Tests\Fixtures\Shelf;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A table of books, a little of everything a table declares.
 */
final readonly class BooksTable implements DataTable
{
    public function __construct(
        private Shelf $shelf,
    ) {
    }

    #[Override]
    public static function name(): string
    {
        return 'books';
    }

    #[Override]
    public function configure(TableBuilder $table): void
    {
        $table
            ->caption('The books')
            ->version('version')
            ->column(Column::text('title', 'Title')->sortable()->editable(new Editing(
                'BOOK_EDIT',
                static function (array|object $row, mixed $value): void {
                    \assert($row instanceof Book && \is_string($value));

                    if ('Refused' === $value) {
                        throw new ActionRefused('No such title.');
                    }

                    $row->title = $value;
                    ++$row->version;
                },
                [new NotBlank(), new Length(max: 40)],
            )))
            ->column(Column::number('pages', 'Pages', 0)->sortable())
            ->column(Column::date('published', 'Published', timezone: 'UTC')->sortable())
            ->column(Column::enum('status', 'Status', Status::class))
            ->column(Column::boolean('inPrint', 'In print'))
            ->column(Column::link('page', 'Page', static fn (array|object $row): string => $row instanceof Book ? '/books/'.$row->id : '')->reading(static fn (): string => 'Open'))
            ->column(Column::block('cover', 'Cover', 'cells.html.twig', 'cover'))
            ->filter(Filter::text('q', 'Search', ['title']))
            ->filter(Filter::enum('status', 'Status', Status::class))
            ->filter(Filter::dateRange('published', 'Published', timezone: 'UTC'))
            ->filter(Filter::boolean('inPrint', 'In print'))
            ->action(Action::row('archive', 'Archive', 'BOOK_ARCHIVE', $this->archive(...))->alsoOnSelection()->destructive())
            ->action(Action::row('touch', 'Touch', 'BOOK_TOUCH', $this->archive(...))->withoutConfirmation())
            ->action(Action::row('refuse', 'Refuse', 'BOOK_TOUCH', static function (): never {
                throw new ActionRefused('Not this one.');
            }))
            ->sortBy('title')
            ->pageSizes(10, 25)
            ->exportable('BOOK_EXPORT', 50);
    }

    #[Override]
    public function source(array $context): DataSource
    {
        return new InMemoryDataSource(\array_slice($this->shelf->books, 0, \is_int($context['limit'] ?? null) ? $context['limit'] : null));
    }

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    private function archive(array $rows): void
    {
        foreach ($rows as $row) {
            \assert($row instanceof Book);
            $this->shelf->archived[] = $row->id;
        }
    }
}
