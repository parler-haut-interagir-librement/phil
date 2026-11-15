<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\Sql;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The repositories on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalMailingListRepository::class)]
#[CoversClass(DbalSubscriberRepository::class)]
#[CoversClass(DbalCampaignRepository::class)]
#[CoversClass(DbalDeliveryRepository::class)]
#[CoversClass(DbalTemplateRepository::class)]
#[CoversClass(DbalEngagementRepository::class)]
#[CoversClass(DbalSuppressionRepository::class)]
#[CoversClass(Sql::class)]
final class PostgreSqlRepositoriesTest extends ServerRepositoriesTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
