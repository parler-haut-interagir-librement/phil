<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\AlmanacBundle\Domain\Model\StoredDate;

/**
 * Gives the key of a stored date the binary collation of MySQL and MariaDB as the ORM loads its metadata, so that the
 * schema it generates, and the migration `doctrine:migrations:diff` writes, compare it byte by byte, as PHP does: a row
 * written `Harvest` or `harvést` outside the bundle — the bundle writes lowercase ASCII keys — is never found for
 * `harvest`, nor stops it from being saved under the unique key. PostgreSQL's default collations are deterministic and
 * SQLite compares bytes: their columns keep the default, and the mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        StoredDate::class => ['key'],
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
