<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Integration;

use Override;
use Phil\ProposalReview\Dbal\DbalCampaignStore;
use Phil\ProposalReview\Dbal\DbalProposalStore;
use Phil\ProposalReview\Dbal\DbalReviewStore;
use Phil\ProposalReview\Dbal\DbalVoteLog;
use Phil\ProposalReview\Dbal\Tables;
use Phil\ProposalReview\Tests\Unit\Dbal\DbalStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The stores on a PostgreSQL server, whose collation may sort differently from byte order: the stores sort in PHP.
 */
#[CoversClass(DbalCampaignStore::class)]
#[CoversClass(DbalProposalStore::class)]
#[CoversClass(DbalReviewStore::class)]
#[CoversClass(DbalVoteLog::class)]
#[CoversClass(Tables::class)]
#[Group('postgresql')]
final class PostgreSqlStoreTest extends DbalStoreTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
