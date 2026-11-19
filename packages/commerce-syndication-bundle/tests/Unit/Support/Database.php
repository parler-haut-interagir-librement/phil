<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\FedSkuIndex;

/**
 * An entity manager on an SQLite database in memory, with the bundle's tables and its flush listener.
 */
final class Database
{
    public static function entityManager(): EntityManager
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $entityManager->getEventManager()->addEventListener(Events::onFlush, new FedSkuIndex());
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
