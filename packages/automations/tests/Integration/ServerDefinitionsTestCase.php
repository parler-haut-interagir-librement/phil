<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Dbal\DbalDefinitions;
use Phil\Automations\Tests\Unit\Definition\DefinitionsTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Every promise of a definitions store on a real server.
 */
abstract class ServerDefinitionsTestCase extends DefinitionsTestCase
{
    private ?Connection $connection = null;

    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    #[Override]
    protected function definitions(): Definitions
    {
        $connection = $this->connection ??= Server::connect($this->variable());
        $store = new DbalDefinitions($connection, new MockClock('2026-11-13 10:00:00 UTC'), 'phil_automation_definition_test');
        Server::reset($connection, 'phil_automation_definition_test', $store->configureSchema(...));

        return $store;
    }
}
