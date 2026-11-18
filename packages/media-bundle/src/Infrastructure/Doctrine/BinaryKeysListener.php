<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;

/**
 * Gives the keys of the bundle's entities the binary collation of MySQL and MariaDB as the ORM loads their metadata, so
 * that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the
 * collection `Press` is never found for `press`, and the file stored under `2026/Photo.jpg` is never taken for
 * `2026/photo.jpg`. The join columns towards a media or a collection take the collation of its identifier.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default, and the
 * mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        MediaCollection::class => ['id', 'key'],
        Media::class => ['id', 'storageKey'],
        MediaVersion::class => ['id', 'storageKey'],
    ];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $fields = self::FIELDS[$metadata->getName()] ?? [];

        if ([] === $fields || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($fields as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        }
    }
}
