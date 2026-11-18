<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;

/**
 * Gives the keys of the bundle's entity the binary collation of MySQL and MariaDB as the ORM loads its metadata, so that
 * the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the
 * transitions scheduled for the resource `Doc-A` are never listed or cancelled for `doc-a`. PostgreSQL's default
 * collations are deterministic and SQLite compares bytes: their columns keep the default, and the mapping stays the
 * same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        ScheduledTransition::class => ['id', 'resourceKind', 'resourceId', 'transition'],
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
