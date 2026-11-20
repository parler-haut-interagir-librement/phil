<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\AiBundle\Tests\Unit\Infrastructure\RepositoryTestCase;

/**
 * The Doctrine journal on a real server read from the environment: its table created from the mapping, the totals
 * summed by the server's grouped query, the erasure.
 */
abstract class ServerRepositoryTestCase extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
    }

    #[Override]
    protected function repository(): JournalRepository
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url)), $configuration);
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return new DoctrineJournalRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
