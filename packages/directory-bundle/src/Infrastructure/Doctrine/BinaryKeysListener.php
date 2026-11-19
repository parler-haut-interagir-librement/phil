<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\DirectoryBundle\Domain\Model\Listing;

/**
 * Gives the genre and the author of a listing the binary collation of MySQL and MariaDB as the ORM loads its metadata,
 * so that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte, as
 * PHP does: the listings of the user `Ann` are never those of `ann`, and a row written in the genre `Podcast` outside
 * the bundle — which writes lowercase genres — is never listed under `podcast`. PostgreSQL's default collations are
 * deterministic and SQLite compares bytes: their columns keep the default, and the mapping stays the same for every
 * platform. The keys of the rows of a listing are those of content-bundle, which gives them the same collation.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        Listing::class => ['genre', 'author'],
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
