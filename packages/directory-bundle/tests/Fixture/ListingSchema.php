<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DirectoryBundle\Infrastructure\Doctrine\EditedByCapabilityMapping;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;

/**
 * The schema of the listing table and of the table of its rows, as the ORM generates it in an application: the mapping
 * listener of content-bundle first, with `EditedByCapabilityMapping` — the only capability whose column these tests
 * read — then, when listening, the bundle's `BinaryKeysListener`.
 */
final class ListingSchema
{
    public const string LISTING_TABLE = 'phil_directory_listing';

    public const string ROW_TABLE = 'phil_directory_listing_dimension';

    public static function of(Connection $connection, bool $listening): Schema
    {
        $types = [
            ListingIdType::NAME => ListingIdType::class,
            DimensionContentIdType::NAME => DimensionContentIdType::class,
            LocaleType::NAME => LocaleType::class,
            StageType::NAME => StageType::class,
            VersionType::NAME => VersionType::class,
        ];

        foreach ($types as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }

        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $events = $entityManager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new DimensionContentMappingListener(ContentRoots::fromClassNames([Listing::class]), [new EditedByCapabilityMapping()]));

        if ($listening) {
            $events->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        }

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), [Listing::class, ListingDimension::class]));
    }
}
