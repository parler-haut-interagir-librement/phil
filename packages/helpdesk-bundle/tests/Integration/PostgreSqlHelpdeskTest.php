<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Integration;

use Override;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The help desk on PostgreSQL 17, at `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalTicketRepository::class)]
#[CoversClass(HelpdeskTables::class)]
#[CoversClass(Helpdesk::class)]
#[Group('mysql')]
final class PostgreSqlHelpdeskTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
