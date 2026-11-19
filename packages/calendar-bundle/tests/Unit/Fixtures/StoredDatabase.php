<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
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
        if (!Type::hasType(RecurrenceRuleColumnType::NAME)) {
            Type::addType(RecurrenceRuleColumnType::NAME, RecurrenceRuleColumnType::class);
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
