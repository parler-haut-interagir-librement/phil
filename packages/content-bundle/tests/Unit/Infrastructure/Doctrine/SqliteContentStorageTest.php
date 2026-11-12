<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentTransaction;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrinePublishedRowRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineRouteRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\RootIdentifiers;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * SQLite in memory, through `pdo_sqlite`: always run, so the bundle installed on its own proves its storage.
 *
 * SQLite leaves foreign keys unchecked unless asked, so the test asks: the cascade from root to rows is part
 * of what is proved.
 */
#[CoversClass(DoctrineDimensionContentRepository::class)]
#[CoversClass(DoctrineContentTransaction::class)]
#[CoversClass(DoctrineContentRootRepository::class)]
#[CoversClass(DoctrinePublishedRowRepository::class)]
#[CoversClass(DoctrineRouteRepository::class)]
#[CoversClass(RootIdentifiers::class)]
#[CoversClass(DimensionContentMappingListener::class)]
#[CoversClass(LocaleType::class)]
#[CoversClass(StageType::class)]
#[CoversClass(VersionType::class)]
final class SqliteContentStorageTest extends ContentStorageTestCase
{
    #[Override]
    protected function connectionParameters(): array
    {
        return ['driver' => 'pdo_sqlite', 'memory' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
    }
}
