<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Benchmark;

use DateTimeImmutable;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Tests\Fixtures\Book;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use Phil\DataTableBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The cost of one render: 100 rows of 10 columns laid out in PHP, then the same table drawn by the whole component
 * through a booted kernel, as a worker draws it request after request.
 *
 * @Groups({"data-table"})
 */
final readonly class TableRenderBench
{
    private OpenTable $open;

    private Kit $kit;

    private Environment $twig;

    public function __construct()
    {
        $this->kit = new Kit();
        $edit = new Editing('EDIT', static function (): void {});
        $builder = new TableBuilder('bench')->caption('Benchmark')->column(Column::text('title', 'Title')->sortable()->editable($edit));

        foreach (['a', 'b', 'c'] as $name) {
            $builder->column(Column::number('pages'.$name, 'Pages')->of('pages')->sortable('pages'));
            $builder->column(Column::date('published'.$name, 'Published')->of('published'));
            $builder->column(Column::enum('status'.$name, 'Status', Status::class)->of('status'));
        }

        $rows = [];

        for ($id = 1; $id <= 100; ++$id) {
            $rows[] = new Book($id, 'Book '.$id, 100 + $id, new DateTimeImmutable('2026-01-02'), Status::Live);
        }

        $this->open = new OpenTable($builder->pageSizes(100)->build(), new InMemoryDataSource($rows));

        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $requests = $container->get('request_stack');
        \assert($requests instanceof RequestStack);
        $requests->push(Request::create('/books'));
        $twig = $container->get('twig');
        \assert($twig instanceof Environment);
        $this->twig = $twig;
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchViewOf100Rows(): void
    {
        $this->kit->views->view($this->open, new TableState());
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchComponent(): void
    {
        $this->twig->render('bench.html.twig');
    }
}
