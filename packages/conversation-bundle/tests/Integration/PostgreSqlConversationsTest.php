<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Integration;

use Override;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The tables and queries on PostgreSQL 17, at `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(DbalReceptionRepository::class)]
#[CoversClass(ConversationTables::class)]
#[CoversClass(KeyRotation::class)]
#[Group('postgresql')]
final class PostgreSqlConversationsTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
