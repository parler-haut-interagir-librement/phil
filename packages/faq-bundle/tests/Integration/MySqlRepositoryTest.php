<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Integration;

use Override;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalSpentVoteTokens;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Unit\Infrastructure\RepositoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, their schema created from the mapping.
 */
#[CoversClass(DoctrineFaqRepository::class)]
#[CoversClass(DbalUnansweredSearches::class)]
#[CoversClass(DbalSpentVoteTokens::class)]
final class MySqlRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function url(): string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        return $url;
    }
}
