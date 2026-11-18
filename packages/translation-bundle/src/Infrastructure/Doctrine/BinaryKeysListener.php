<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Model\MemoryRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;

/**
 * Gives the keys of the bundle's entities the binary collation of MySQL and MariaDB as the ORM loads their metadata, so
 * that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the
 * segment `Title` of a document is never `title`, the glossary holds `Apple` and `apple` as two terms, and the
 * work of the translator `Ann` is never listed for `ann`. PostgreSQL's default collations are deterministic and SQLite
 * compares bytes: their columns keep the default, and the mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        SegmentRecord::class => ['document', 'sourceLanguage', 'targetLanguage', 'segmentKey', 'translatedBy', 'reviewedBy'],
        MemoryRecord::class => ['sourceLanguage', 'targetLanguage', 'fingerprint'],
        TermRecord::class => ['sourceLanguage', 'targetLanguage', 'term'],
        CommentRecord::class => ['document', 'targetLanguage', 'segmentKey', 'author'],
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
