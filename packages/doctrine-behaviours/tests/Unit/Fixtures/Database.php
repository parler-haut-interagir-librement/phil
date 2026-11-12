<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use Phil\DoctrineBehaviours\BlameListener;
use Phil\DoctrineBehaviours\Contract\BlameResolver;
use Phil\DoctrineBehaviours\Contract\OwnerResolver;
use Phil\DoctrineBehaviours\DatedIdentifierListener;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\DeactivationListener;
use Phil\DoctrineBehaviours\FixedOwner;
use Phil\DoctrineBehaviours\OwnerListener;
use Phil\DoctrineBehaviours\PositionListener;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\SoftDeleteListener;
use Phil\DoctrineBehaviours\TimestampListener;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\DoctrineBehaviours\TreeListener;
use Phil\DoctrineBehaviours\VersionListener;
use Phil\Slug\Sluggers;
use Phil\Slug\TransliteratingSlugger;
use Psr\Clock\ClockInterface;

/**
 * An entity manager on an SQLite database in memory, with the fixtures' tables, every listener and the filter;
 * values fetched as strings when asked, as other drivers fetch numbers.
 */
final class Database
{
    public static function entityManager(ClockInterface $clock, BlameResolver $blame, bool $stringFetches = false, ?OwnerResolver $owner = null, ?Middleware $middleware = null): EntityManager
    {
        return self::onConnection($clock, $blame, $owner, ['driver' => 'pdo_sqlite', 'memory' => true, 'driverOptions' => [PDO::ATTR_STRINGIFY_FETCHES => $stringFetches]], $middleware);
    }

    /**
     * @param array<string, mixed> $parameters what `DriverManager` takes
     */
    public static function onConnection(ClockInterface $clock, BlameResolver $blame, ?OwnerResolver $owner, array $parameters, ?Middleware $middleware = null): EntityManager
    {
        if (!Type::hasType(PrefixedType::NAME)) {
            Type::addType(PrefixedType::NAME, PrefixedType::class);
        }

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Entity', \dirname(__DIR__, 3).'/src/Version']));
        $config->enableNativeLazyObjects(true);
        $config->addFilter(SoftDeletedFilter::NAME, SoftDeletedFilter::class);
        $config->addFilter(DeactivatedFilter::NAME, DeactivatedFilter::class);

        if ($middleware instanceof Middleware) {
            $config->setMiddlewares([$middleware]);
        }

        /** @phpstan-ignore argument.type (the parameters of a test, as DriverManager documents them) */
        $manager = new EntityManager(DriverManager::getConnection($parameters, $config), $config);
        $events = $manager->getEventManager();
        $events->addEventListener([Events::prePersist, Events::onFlush], new TimestampListener($clock));
        $events->addEventListener([Events::prePersist, Events::onFlush], new BlameListener($blame));
        $events->addEventListener([Events::onFlush], new SoftDeleteListener($clock));
        $events->addEventListener([Events::postPersist, Events::onFlush, Events::postFlush], new TreeListener());
        $events->addEventListener([Events::prePersist, Events::onFlush], new DeactivationListener($clock));
        $events->addEventListener([Events::prePersist], new OwnerListener($owner ?? new FixedOwner()));
        $events->addEventListener([Events::onFlush], new SlugListener(new Sluggers(['default' => new TransliteratingSlugger(), 'short' => new TransliteratingSlugger(maxLength: 8)], 'default')));
        $events->addEventListener([Events::onFlush], new VersionListener());
        $events->addEventListener([Events::onFlush], new PositionListener());
        $events->addEventListener([Events::prePersist], new DatedIdentifierListener($clock));
        $events->addEventListener([Events::loadClassMetadata], new TranslationListener());
        $manager->getFilters()->enable(SoftDeletedFilter::NAME);
        $manager->getFilters()->enable(DeactivatedFilter::NAME);
        $schema = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        return $manager;
    }
}
