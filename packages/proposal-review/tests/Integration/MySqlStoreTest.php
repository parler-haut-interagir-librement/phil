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
 * The stores on a MySQL server, whose default collation is blind to case and to trailing spaces: identifiers keep theirs.
 */
#[CoversClass(DbalCampaignStore::class)]
#[CoversClass(DbalProposalStore::class)]
#[CoversClass(DbalReviewStore::class)]
#[CoversClass(DbalVoteLog::class)]
#[CoversClass(Tables::class)]
#[Group('mysql')]
final class MySqlStoreTest extends DbalStoreTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
