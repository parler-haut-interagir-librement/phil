<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Integration;

use Override;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\MentionsBundle\Infrastructure\Doctrine\MentionInsert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * MySQL 8, through `pdo_mysql`: `phil-dev services test` gives it `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DoctrineMentionRepository::class)]
#[CoversClass(MentionInsert::class)]
#[Group('mysql')]
final class MySqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) ? $url : null;
    }
}
