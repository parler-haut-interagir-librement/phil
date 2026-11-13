<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Dbal\DbalRunStore;
use Phil\Automations\Tests\Unit\Run\RunStoreTestCase;

/**
 * Every promise of a run store on a real server, and the save that only one of two connections wins.
 */
abstract class ServerRunStoreTestCase extends RunStoreTestCase
{
    private ?Connection $connection = null;

    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testOfTwoConnectionsOneSavesTheRun(): void
    {
        $store = $this->store();
        $run = self::newRun('order:1');
        $store->create($run);
        $other = Server::connect($this->variable());

        self::assertTrue(new DbalRunStore($other, 'phil_automation_run_test')->save($run->movedTo(null, self::at(1))));
        self::assertFalse($store->save($run->movedTo(null, self::at(2))));
        self::assertFalse(new DbalRunStore($other, 'phil_automation_run_test')->create($run));
        $other->close();
    }

    #[Override]
    protected function store(): RunStore
    {
        $connection = $this->connection ??= Server::connect($this->variable());
        $store = new DbalRunStore($connection, 'phil_automation_run_test');
        Server::reset($connection, 'phil_automation_run_test', $store->configureSchema(...));

        return $store;
    }
}
