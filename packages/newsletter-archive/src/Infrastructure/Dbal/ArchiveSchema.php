<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;

/**
 * The tables of the archive, added to a schema that lacks them — the one a migration builds, or the one the ORM
 * generates, through `ArchiveSchemaListener`. Instants are `datetime_utc_immutable`: written and read in UTC.
 *
 * `phil_newsletter_archive` holds one row per archived campaign, `phil_newsletter_archive_tag` its tags. On MySQL and
 * MariaDB, the keys, slugs, tags and hashes compare byte by byte, under `utf8mb4_bin`.
 */
final class ArchiveSchema
{
    public const string ISSUES = 'phil_newsletter_archive';

    public const string TAGS = 'phil_newsletter_archive_tag';

    public const array TABLES = [self::ISSUES, self::TAGS];

    private const array BINARY_COLUMNS = ['campaign_id', 'site', 'slug', 'list_id', 'list_key', 'link_hash', 'tag'];

    /**
     * Adds the tables the schema lacks.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables($platform instanceof AbstractMySQLPlatform) as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    private static function tables(bool $binary): array
    {
        return [
            Table::editor()
                ->setUnquotedName(self::ISSUES)
                ->setColumns(
                    self::string('campaign_id', 36, false, $binary),
                    self::string('site', 64, false, $binary),
                    self::string('slug', 255, false, $binary),
                    self::string('list_id', 36, false, $binary),
                    self::string('list_key', 64, false, $binary),
                    self::string('list_name', 120, false, $binary),
                    self::string('subject', 200, false, $binary),
                    self::string('preheader', 255, false, $binary),
                    self::column('html', Types::TEXT),
                    self::column('sent_at', Types::DATETIME_UTC_IMMUTABLE),
                    self::column('rendered_at', Types::DATETIME_UTC_IMMUTABLE),
                    self::column('published_at', Types::DATETIME_UTC_IMMUTABLE, true),
                    self::string('link_hash', 64, true, $binary),
                    self::column('link_issued_at', Types::DATETIME_UTC_IMMUTABLE, true),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('campaign_id')->create())
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName(self::ISSUES.'_slug')->setUnquotedColumnNames('site', 'slug')->create())
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName(self::ISSUES.'_link')->setUnquotedColumnNames('link_hash')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::ISSUES.'_published')->setUnquotedColumnNames('site', 'published_at', 'sent_at')->create())
                ->create(),
            Table::editor()
                ->setUnquotedName(self::TAGS)
                ->setColumns(self::string('campaign_id', 36, false, $binary), self::string('tag', 64, false, $binary))
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('campaign_id', 'tag')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::TAGS.'_tag')->setUnquotedColumnNames('tag', 'campaign_id')->create())
                ->create(),
        ];
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $nullable, bool $binary): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($binary && \in_array($name, self::BINARY_COLUMNS, true)) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setNotNull(!$nullable)->create();
    }
}
