<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\RecurrenceBundle\Infrastructure\Doctrine\RecurrenceRuleColumnType;

/**
 * An entity manager on an SQLite database in memory, with the tables of the calendar: what reads the translations of
 * events and venues reads them from rows.
 */
final class StoredDatabase
{
    public static function entityManager(): EntityManager
    {
        // A configuration without a type provider of its own gives the static registry of DBAL.
        $registry = new DbalConfiguration()->getTypeProvider();
        \assert($registry instanceof TypeRegistry);

        if (!$registry->has(RecurrenceRuleColumnType::NAME)) {
            $registry->register(RecurrenceRuleColumnType::NAME, RecurrenceRuleColumnType::class);
        }

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model']));
        $config->enableNativeLazyObjects(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Phil\CalendarBundle\Tests\Proxies');

        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $manager->getEventManager()->addEventListener([Events::loadClassMetadata], new TranslationListener());
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return $manager;
    }
}
