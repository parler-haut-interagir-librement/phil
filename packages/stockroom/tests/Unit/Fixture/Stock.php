<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit\Fixture;

use Phil\Stockroom\Deployments;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Memory\InMemoryLoadLists;
use Phil\Stockroom\Stockroom;
use Symfony\Component\Clock\MockClock;

/**
 * A stockroom on the stores in memory, its clock stopped in Montréal at 2026-11-13 09:00.
 */
final readonly class Stock
{
    public InMemoryContainers $containers;

    public InMemoryItems $items;

    public InMemoryBalances $balances;

    public InMemoryJournal $journal;

    public InMemoryLoadLists $lists;

    public MockClock $clock;

    public Stockroom $stockroom;

    public Deployments $deployments;

    public function __construct()
    {
        $this->containers = new InMemoryContainers();
        $this->items = new InMemoryItems();
        $this->balances = new InMemoryBalances();
        $this->journal = new InMemoryJournal();
        $this->lists = new InMemoryLoadLists();
        $this->clock = new MockClock('2026-11-13 09:00:00', 'America/Toronto');
        $this->stockroom = new Stockroom($this->containers, $this->items, $this->balances, $this->journal, $this->clock);
        $this->deployments = new Deployments($this->stockroom, $this->lists, $this->balances);
    }
}
