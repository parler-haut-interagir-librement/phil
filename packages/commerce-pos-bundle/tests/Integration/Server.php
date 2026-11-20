<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CommercePosBundle\Infrastructure\Doctrine\DoctrinePosRepository;

/**
 * An entity manager of the module's mapping on the database of a URL: SQLite in memory, or a server.
 */
final readonly class Server
{
    public function __construct(
        public EntityManager $entityManager,
    ) {
    }

    public static function connect(string $url): self
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($parameters), $configuration));
    }

    /**
     * Drops the module's tables, if they are there, and creates them again from the mapping.
     */
    public function recreate(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function repository(): DoctrinePosRepository
    {
        return new DoctrinePosRepository($this->entityManager);
    }

    /**
     * Writes what is pending and forgets every entity, so that the next read comes from the database.
     */
    public function reload(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
