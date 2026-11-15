<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\NewsletterBundle\Infrastructure\Dbal\Sql;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use UnexpectedValueException;

#[CoversClass(DbalMailingListRepository::class)]
#[CoversClass(DbalSubscriberRepository::class)]
#[CoversClass(DbalCampaignRepository::class)]
#[CoversClass(DbalDeliveryRepository::class)]
#[CoversClass(DbalTemplateRepository::class)]
#[CoversClass(DbalEngagementRepository::class)]
#[CoversClass(DbalSuppressionRepository::class)]
#[CoversClass(NewsletterSchema::class)]
#[CoversClass(Sql::class)]
final class SqliteRepositoriesTest extends RepositoriesTestCase
{
    public function testTheSchemaIsAddedOnce(): void
    {
        $manager = $this->newsletter->connection->createSchemaManager();
        $schema = $manager->introspectSchema();

        self::assertEqualsCanonicalizing(NewsletterSchema::TABLES, array_values(array_filter(array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables()), static fn (string $name): bool => str_starts_with($name, 'phil_newsletter_'))));
        self::assertCount(\count($schema->getTables()), NewsletterSchema::configure($schema)->getTables());
        self::assertFalse($schema->getTable(NewsletterSchema::SUBSCRIBERS)->getColumn('external_id')->getNotnull());
        self::assertTrue($schema->getTable(NewsletterSchema::SUBSCRIBERS)->getColumn('email')->getNotnull());
    }

    public function testTheColumnsAreRead(): void
    {
        self::assertSame(3, Sql::integer('3'));
        self::assertSame('x', Sql::string('x'));
        self::assertNull(Sql::nullableString(null));
        self::assertNull(Sql::nullableInstant(null));
        self::assertSame(5, Sql::nullableInstant('5')?->getTimestamp());
        $refused = 0;

        foreach ([static fn (): int => Sql::integer('x'), static fn (): string => Sql::string(1)] as $read) {
            try {
                $read();
            } catch (UnexpectedValueException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    #[Override]
    protected function newsletter(): Newsletter
    {
        return new Newsletter();
    }
}
