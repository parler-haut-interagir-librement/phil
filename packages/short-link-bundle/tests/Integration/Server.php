<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ShortLinkBundle\Infrastructure\Doctrine\DoctrineShortLinkRepository;

/**
 * An entity manager of the bundle's mapping on the server of a URL, for the integration tests and the processes they
 * start.
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

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);

        return new self(new EntityManager(DriverManager::getConnection($parameters), $configuration));
    }

    /**
     * Drops the bundle's table, if it is there, and creates it again from the mapping.
     */
    public function recreate(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function repository(): DoctrineShortLinkRepository
    {
        return new DoctrineShortLinkRepository($this->entityManager);
    }
}
