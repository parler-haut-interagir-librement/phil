<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Model\LeasedResource;

/**
 * Makes the keys of the lease tables compare byte for byte on MySQL and MariaDB, whose default collations fold case
 * and accents: the resource, the part and the holder's identifier take `utf8mb4_bin`, so that the blocks `aB3` and
 * `ab3`, or the resources `Page` and `page`, are leased apart. The other platforms compare strings exactly already, and
 * their mapping is left as it is.
 */
final class BinaryKeysListener
{
    /** The fields compared byte for byte, by entity. */
    private const array FIELDS = [
        Lease::class => ['resource', 'part', 'holderId'],
        LeasedResource::class => ['resource'],
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
            $mapping->options = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'] + ($mapping->options ?? []);
        }
    }
}
