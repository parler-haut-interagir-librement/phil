<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\Content\SingleRoot;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Routing\Route;

/**
 * Gives the keys of the bundle's entities, and of the application's rows, the binary collation of MySQL and MariaDB as
 * the ORM loads their metadata, so that the schema it generates, and the migration `doctrine:migrations:diff` writes,
 * compare them byte by byte: the route `/news/Doc-A` never answers `/news/doc-a`, the content `news:Doc-A` is never
 * `news:doc-a`, and the autosave of the author `Ann` is never given to `ann`. PostgreSQL's default collations are
 * deterministic and SQLite compares bytes: their columns keep the default, and the mapping stays the same for every
 * platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity of the bundle.
     */
    public const array FIELDS = [
        Route::class => ['resourceKey', 'resourceId', 'slug', 'locale'],
        SingleRoot::class => ['resourceKey', 'resourceId'],
        WorkingCopy::class => ['resourceKey', 'resourceId', 'locale', 'author'],
    ];

    /**
     * The fields that compare byte by byte on every subclass of `AbstractDimensionContent`: those of its unique key.
     */
    public const array DIMENSION_FIELDS = ['locale', 'localeKey', 'stage'];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $fields = self::FIELDS[$metadata->getName()] ?? (is_subclass_of($metadata->getName(), AbstractDimensionContent::class) ? self::DIMENSION_FIELDS : []);

        if ([] === $fields || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($fields as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        }
    }
}
