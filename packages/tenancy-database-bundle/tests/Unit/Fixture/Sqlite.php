<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;

/**
 * An entity manager on an in-memory SQLite, over the entities of one fixture directory.
 */
final class Sqlite
{
    public static function entityManager(string $directory): EntityManager
    {
        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([$directory]));
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
    }
}
