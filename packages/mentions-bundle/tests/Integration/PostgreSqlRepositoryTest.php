<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Integration;

use Override;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\MentionsBundle\Infrastructure\Doctrine\MentionInsert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineMentionRepository::class)]
#[CoversClass(MentionInsert::class)]
#[Group('postgresql')]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) ? $url : null;
    }
}
