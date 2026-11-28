<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineUpdatePlanRepository;

/**
 * The Doctrine stores on a connection, their tables dropped and created from the mapping.
 */
abstract class DoctrineRepositoryTestCase extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
        $this->entityManager = null;
    }

    abstract protected function connection(): Connection;

    protected function entityManager(): EntityManager
    {
        if (!$this->entityManager instanceof EntityManager) {
            $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
            $configuration->enableNativeLazyObjects(true);
            $this->entityManager = new EntityManager($this->connection(), $configuration);
            $tool = new SchemaTool($this->entityManager);
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }

        return $this->entityManager;
    }

    #[Override]
    protected function sites(): SiteRepository
    {
        return new DoctrineSiteRepository($this->entityManager());
    }

    #[Override]
    protected function snapshots(): SnapshotRepository
    {
        return new DoctrineSnapshotRepository($this->entityManager());
    }

    #[Override]
    protected function pushes(): ConfigurationPushRepository
    {
        return new DoctrineConfigurationPushRepository($this->entityManager());
    }

    #[Override]
    protected function plans(): UpdatePlanRepository
    {
        return new DoctrineUpdatePlanRepository($this->entityManager());
    }

    #[Override]
    protected function journal(): JournalRepository
    {
        return new DoctrineJournalRepository($this->entityManager());
    }

    #[Override]
    protected function rotations(): KeyRotationRepository
    {
        return new DoctrineKeyRotationRepository($this->entityManager());
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager()->clear();
    }
}
