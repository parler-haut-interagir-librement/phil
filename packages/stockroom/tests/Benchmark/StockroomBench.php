<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Benchmark;

use Generator;
use Phil\Stockroom\Deployments;
use Phil\Stockroom\LoadListReader;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Memory\InMemoryLoadLists;
use Phil\Stockroom\Place;
use Phil\Stockroom\Stockroom;
use Symfony\Component\Clock\MockClock;

/**
 * The hot paths of a stockroom: a load list of so many lines read from text, then deployed and checked at the return,
 * each line's item spread over a few containers.
 *
 * @BeforeMethods("setUp")
 */
final class StockroomBench
{
    private Stockroom $stockroom;

    private Deployments $deployments;

    private LoadListReader $reader;

    private string $text;

    /** @var array<string, int> */
    private array $counted = [];

    public function __construct()
    {
        $this->setUp(['lines' => 1]);
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $balances = new InMemoryBalances();
        $items = new InMemoryItems();
        $this->stockroom = new Stockroom(new InMemoryContainers(), $items, $balances, new InMemoryJournal(), new MockClock());
        $this->deployments = new Deployments($this->stockroom, new InMemoryLoadLists(), $balances);
        $this->reader = new LoadListReader($items);

        foreach (range(1, 4) as $crate) {
            $this->stockroom->addContainer('Crate '.$crate, id: 'crate-'.$crate);
        }

        $this->text = '';
        $this->counted = [];

        foreach (range(1, $params['lines']) as $line) {
            $item = $this->stockroom->addItem('item-'.$line, 'Item '.$line, home: 'crate-1', id: 'id-'.$line);

            foreach (range(1, 4) as $crate) {
                $this->stockroom->receive($item->id, 'crate-'.$crate, 3);
            }

            $this->text .= "10 x item-{$line}\n";
            $this->counted[$item->id] = 9;
        }
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function provideLines(): Generator
    {
        yield '10 lines' => ['lines' => 10];
        yield '500 lines' => ['lines' => 500];
    }

    /**
     * @ParamProviders("provideLines")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadDeployAndCheckIn(): void
    {
        $this->deployments->plan('event', $this->reader->read($this->text));
        $this->deployments->deploy('event');
        $this->deployments->checkIn('event', $this->counted, true);
    }

    /**
     * @ParamProviders("provideLines")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchIssueAndGiveBack(): void
    {
        $this->stockroom->issue('id-1', Place::holder('loan'), 10);
        $this->stockroom->giveBack('id-1', Place::holder('loan'), 10);
    }
}
