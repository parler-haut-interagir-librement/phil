<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of the trail: one row per place in a segment, never updated. The database role that writes it should be
 * allowed to insert and select only. Its facts are columns of their own, each indexed with the key of the order rows
 * are read in — instant, segment, sequence —, so that the log is read by actor, action, record or tenant, a page at a
 * time, without scanning it or sorting it.
 *
 * The tenant, the actor and the record's identifier compare byte by byte: they take the binary collation of MySQL and
 * MariaDB (`utf8mb4_bin`) and the `C` collation of PostgreSQL, so that the rows of `acme` are never read for `ACME`
 * or `acmé`, as a server's default collation (`utf8mb4_0900_ai_ci`) would read them. SQLite compares them byte by
 * byte already.
 */
final class AuditTable
{
    public const string NAME = 'phil_audit_row';

    /** The table of the feeds' places: one row per feed and segment, the sequence of the last row sent. */
    public const string FEED = 'phil_audit_feed';

    /**
     * Adds the trail's table and the feeds' table to the schema, each when it is not there yet, for the platform that
     * will create them.
     */
    public static function configure(Schema $schema, AbstractPlatform $platform): Schema
    {
        $binary = self::binaryCollation($platform);

        if (!$schema->hasTable(self::FEED)) {
            $schema = $schema->edit()->addTable(Table::editor()
                ->setUnquotedName(self::FEED)
                ->setColumns(
                    self::string('feed', 64),
                    self::string('segment', 64),
                    Column::editor()->setUnquotedName('sequence')->setTypeName(Types::BIGINT)->create(),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('feed', 'segment')->create())
                ->create())->create();
        }

        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->setColumns(
                self::string('segment', 64),
                Column::editor()->setUnquotedName('sequence')->setTypeName(Types::BIGINT)->create(),
                self::string('kind', 16),
                // As text, to the microsecond: the hash covers the instant exactly, and a date type would round it.
                self::string('recorded_at', 27, true),
                self::string('serialization_version', 32),
                Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(),
                self::string('previous_hash', 64, true),
                self::string('hash', 64, true),
                self::nullable('action', 64),
                self::nullable('actor', 128, $binary),
                self::nullable('subject_type', 64),
                self::nullable('subject_id', 128, $binary),
                self::nullable('tenant', 255, $binary),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('segment', 'sequence')->create())
            ->setIndexes(
                self::index('recorded', 'recorded_at', 'segment', 'sequence'),
                self::index('actor', 'actor', 'recorded_at', 'segment', 'sequence'),
                self::index('action', 'action', 'recorded_at', 'segment', 'sequence'),
                self::index('subject', 'subject_type', 'subject_id', 'recorded_at', 'segment', 'sequence'),
                self::index('tenant', 'tenant', 'recorded_at', 'segment', 'sequence'),
            )
            ->create())->create();
    }

    /**
     * The collation that compares text byte by byte on that platform; `null` where the default one does.
     *
     * @return non-empty-string|null
     */
    public static function binaryCollation(AbstractPlatform $platform): ?string
    {
        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'utf8mb4_bin',
            $platform instanceof PostgreSQLPlatform => 'C',
            default => null,
        };
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string|null $collation
     */
    private static function nullable(string $name, int $length, ?string $collation = null): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(false);

        if (null !== $collation) {
            $column->setCollation($collation);
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $first
     * @param non-empty-string ...$others
     */
    private static function index(string $name, string $first, string ...$others): Index
    {
        return Index::editor()->setUnquotedName(self::NAME.'_'.$name)->setUnquotedColumnNames($first, ...$others)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $fixed = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setFixed($fixed)->create();
    }
}
