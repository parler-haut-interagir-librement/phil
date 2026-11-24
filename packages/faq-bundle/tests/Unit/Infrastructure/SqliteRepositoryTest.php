<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalSpentVoteTokens;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on SQLite in memory.
 */
#[CoversClass(DoctrineFaqRepository::class)]
#[CoversClass(DbalUnansweredSearches::class)]
#[CoversClass(DbalSpentVoteTokens::class)]
final class SqliteRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite3:///:memory:';
    }
}
