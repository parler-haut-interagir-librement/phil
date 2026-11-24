<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * An entity manager on the bundle's mapping, its tables created again from it: SQLite in memory, or a real server.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function entityManager(string $url = 'sqlite3:///:memory:'): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);
        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite3' => 'pdo_sqlite']);
        $entityManager = new EntityManager(DriverManager::getConnection($parser->parse($url)), $configuration);
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return $entityManager;
    }
}
