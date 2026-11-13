<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Integration;

use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL 17, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalActivationStore::class)]
#[CoversClass(IncidentTables::class)]
#[CoversClass(Activation::class)]
#[Group('postgresql')]
final class PostgreSqlStoreTest extends ServerStoreTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) ? $url : null;
    }
}
