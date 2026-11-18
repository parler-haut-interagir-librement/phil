<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;

/**
 * Gives the keys of the bundle's entities the binary collation of MySQL and MariaDB as the ORM loads their metadata, so
 * that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the tag
 * `Doc-A` is never found for `doc-a`, and the terms of the resource `Doc-A` are never listed for `doc-a`. The join
 * columns towards a category take the collation of its identifier. PostgreSQL's default collations are deterministic
 * and SQLite compares bytes: their columns keep the default, and the mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        Tag::class => ['id', 'slug'],
        Category::class => ['id', 'key', 'path'],
        CategoryTranslation::class => ['id', 'locale'],
        Assignment::class => ['id', 'termType', 'termId', 'resourceKind', 'resourceId'],
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
