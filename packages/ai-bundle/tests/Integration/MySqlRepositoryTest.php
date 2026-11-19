<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Override;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine journal on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DoctrineJournalRepository::class)]
final class MySqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
