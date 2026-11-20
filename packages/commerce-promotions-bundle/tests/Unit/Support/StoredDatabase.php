<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\DoctrineBehaviours\TranslationListener;

/**
 * An entity manager on an SQLite database in memory, with the tables of the module: what reads the translations reads
 * them from rows.
 */
final class StoredDatabase
{
    public static function entityManager(): EntityManager
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Phil\CommercePromotionsBundle\Tests\Proxies');

        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $manager->getEventManager()->addEventListener([Events::loadClassMetadata], new TranslationListener());
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }
}
