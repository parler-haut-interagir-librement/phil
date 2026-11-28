<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Content;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;

/**
 * Gives the site, the key and the locale of a template root the binary collation of MySQL and MariaDB as the ORM loads its
 * metadata, so that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by
 * byte, as PHP does: the site `Shop` is never `shop`. PostgreSQL's default collations are deterministic and SQLite
 * compares bytes: their columns keep the default, and the mapping stays the same for every platform.
 */
final readonly class TemplateKeysCollation
{
    public const array FIELDS = ['site', 'templateKey', 'locale'];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (EmailTemplateContent::class !== $metadata->getName() || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach (self::FIELDS as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        }
    }
}
