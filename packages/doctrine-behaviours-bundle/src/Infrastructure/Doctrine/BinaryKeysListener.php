<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\DatedIdentifiable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Contract\TreeNode;
use Phil\DoctrineBehaviours\Version\RecordVersion;

/**
 * Gives the keys the behaviours write the binary collation of MySQL and MariaDB as the ORM loads the metadata of the
 * classes that take them, so that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare
 * them byte by byte: the slug `Doc-A` is never found for `doc-a` and both can be taken, the translation `fr-CA` is
 * never read for `fr-ca`, and the records of the owner `Ann` are never listed or erased for `ann`. A field is changed
 * only where the class maps it as a string or a GUID; a field the class maps under another type keeps its mapping.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default, and the
 * mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by interface of the behaviours or class of the library.
     */
    public const array FIELDS = [
        Sluggable::class => ['slug'],
        Translation::class => ['locale'],
        Ownable::class => ['ownerId'],
        Blameable::class => ['createdBy', 'updatedBy'],
        TreeNode::class => ['treePath'],
        DatedIdentifiable::class => ['id'],
        RecordVersion::class => ['subjectTable', 'subjectId'],
    ];

    /**
     * The mapped types a key may have: a character column, which a collation applies to.
     */
    private const array TYPES = [Types::STRING, Types::GUID];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $fields = [];

        foreach (self::FIELDS as $type => $names) {
            if (is_a($metadata->getName(), $type, true)) {
                $fields = [...$fields, ...$names];
            }
        }

        if ([] === $fields || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($fields as $field) {
            if (!$metadata->hasField($field)) {
                continue;
            }

            $mapping = $metadata->getFieldMapping($field);

            if (\in_array($mapping->type, self::TYPES, true)) {
                $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
            }
        }
    }
}
