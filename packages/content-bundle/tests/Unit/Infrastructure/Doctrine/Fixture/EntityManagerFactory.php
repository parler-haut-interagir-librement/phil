<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\BodyCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\ExcerptCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\LinkCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\RoutableCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\SeoCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\TemplateCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\WorkflowCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\RouteIdType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;

/**
 * An entity manager wired the way the bundle wires one under DoctrineBundle: its DBAL types, the attribute
 * mapping of its base classes and of the fixtures, and the mapping listener.
 *
 * Reading metadata opens no connection, so the metadata tests use it with SQLite parameters and never query.
 *
 * @phpstan-import-type Params from DriverManager
 */
final class EntityManagerFactory
{
    /**
     * @param Params                           $connection
     * @param list<class-string>|null          $roots        the declared roots; all the example roots by default
     * @param iterable<CapabilityMapping>|null $capabilities every one of the bundle's by default
     */
    public static function create(
        array $connection = ['driver' => 'pdo_sqlite', 'memory' => true],
        ?array $roots = null,
        ?iterable $capabilities = null,
        ?NamingStrategy $namingStrategy = null,
    ): EntityManager {
        self::registerTypes();

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([
            __DIR__,
            \dirname(__DIR__, 5).'/src/Domain/Content',
            \dirname(__DIR__, 5).'/src/Domain/Routing',
        ]));
        $config->enableNativeLazyObjects(true);

        if ($namingStrategy instanceof NamingStrategy) {
            $config->setNamingStrategy($namingStrategy);
        }

        $entityManager = new EntityManager(DriverManager::getConnection($connection, $config), $config);
        $entityManager->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            new DimensionContentMappingListener(
                ContentRoots::fromClassNames($roots ?? [Article::class, Page::class, Note::class, Brief::class, Memo::class]),
                $capabilities ?? self::capabilityMappings(),
            ),
        );

        return $entityManager;
    }

    /**
     * @return list<CapabilityMapping>
     */
    public static function capabilityMappings(): array
    {
        return [
            new TemplateCapabilityMapping(),
            new WorkflowCapabilityMapping(),
            new SeoCapabilityMapping(),
            new ExcerptCapabilityMapping(),
            new LinkCapabilityMapping(),
            new RoutableCapabilityMapping(),
            new BodyCapabilityMapping(),
        ];
    }

    private static function registerTypes(): void
    {
        $types = [
            ArticleIdType::NAME => ArticleIdType::class,
            DimensionContentIdType::NAME => DimensionContentIdType::class,
            LocaleType::NAME => LocaleType::class,
            RouteIdType::NAME => RouteIdType::class,
            StageType::NAME => StageType::class,
            VersionType::NAME => VersionType::class,
        ];

        foreach ($types as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }
}
