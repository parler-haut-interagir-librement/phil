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
 * The tables and queries on MySQL 8, at `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(DbalReceptionRepository::class)]
#[CoversClass(ConversationTables::class)]
#[CoversClass(KeyRotation::class)]
#[Group('mysql')]
final class MySqlConversationsTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
