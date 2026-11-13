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
 * MySQL 8.4, through `pdo_mysql`: `phil-dev services test` gives it `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalActivationStore::class)]
#[CoversClass(IncidentTables::class)]
#[CoversClass(Activation::class)]
#[Group('mysql')]
final class MySqlStoreTest extends ServerStoreTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) ? $url : null;
    }
}
