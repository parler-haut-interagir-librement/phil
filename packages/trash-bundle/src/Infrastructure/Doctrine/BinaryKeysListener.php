<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * Gives the keys of the bundle's entity the binary collation of MySQL and MariaDB as the ORM loads its metadata, so that
 * the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the trashed
 * resource `Doc-A` is never found, restored or purged for `doc-a`. PostgreSQL's default collations are deterministic
 * and SQLite compares bytes: their columns keep the default, and the mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        TrashItem::class => ['id', 'resourceKey', 'resourceId', 'parent', 'trashedBy'],
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
