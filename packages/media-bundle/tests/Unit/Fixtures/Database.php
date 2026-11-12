<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\MediaBundle\Infrastructure\Doctrine\MediaType;

/**
 * An entity manager on an SQLite database in memory, with the bundle's tables, and those of the test records.
 */
final class Database
{
    /**
     * @param bool             $records     with the test entities that show media in columns of type `phil_media`
     * @param list<Middleware> $middlewares around the connection, to watch its queries
     */
    public static function entityManager(bool $records = false, array $middlewares = []): EntityManager
    {
        if (!Type::hasType(MediaType::NAME)) {
            Type::addType(MediaType::NAME, MediaType::class);
        }

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver($records ? [\dirname(__DIR__, 3).'/src/Domain/Model', __DIR__.'/Entity'] : [\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);
        $config->setMiddlewares($middlewares);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
