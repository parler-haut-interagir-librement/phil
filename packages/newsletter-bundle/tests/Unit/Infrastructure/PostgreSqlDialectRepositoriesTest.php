<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSendRunRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\NewsletterBundle\Infrastructure\Dbal\Sql;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The repositories speaking the dialect of PostgreSQL — `ON CONFLICT (key) DO NOTHING` — to an SQLite file, which reads
 * it: a key that is not the one of a unique index fails there as it would on the server.
 */
#[CoversClass(DbalMailingListRepository::class)]
#[CoversClass(DbalSubscriberRepository::class)]
#[CoversClass(DbalCampaignRepository::class)]
#[CoversClass(DbalDeliveryRepository::class)]
#[CoversClass(DbalSendRunRepository::class)]
#[CoversClass(DbalTemplateRepository::class)]
#[CoversClass(DbalEngagementRepository::class)]
#[CoversClass(DbalSuppressionRepository::class)]
#[CoversClass(NewsletterSchema::class)]
#[CoversClass(Sql::class)]
final class PostgreSqlDialectRepositoriesTest extends RepositoriesTestCase
{
    private string $path = '';

    #[Override]
    protected function tearDown(): void
    {
        if ('' !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }
    }

    #[Override]
    protected function newsletter(): Newsletter
    {
        $path = tempnam(sys_get_temp_dir(), 'newsletter');
        self::assertIsString($path);
        $this->path = $path;
        new Newsletter(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]));

        return new Newsletter($this->postgres($path), false);
    }

    private function postgres(string $path): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path], new Configuration()->setMiddlewares([new class implements Middleware {
            #[Override]
            public function wrap(Driver $driver): Driver
            {
                return new class($driver) extends AbstractDriverMiddleware {
                    #[Override]
                    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
                    {
                        return new PostgreSQLPlatform();
                    }
                };
            }
        }]));
    }
}
