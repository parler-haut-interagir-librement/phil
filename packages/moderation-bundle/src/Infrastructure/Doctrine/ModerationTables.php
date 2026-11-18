<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * The five tables of the bundle, added to the schema Doctrine generates so that the application's migrations create
 * them; the moments in UTC (`datetime_utc_immutable`):
 *
 * - `phil_moderation_report`: the reports, one row each, closed by the identifier of a decision;
 * - `phil_moderation_decision`: the decisions, only ever added;
 * - `phil_moderation_note`: the notes of the moderation on members;
 * - `phil_moderation_sandbox`: the members who left the sandbox of the first post;
 * - `phil_moderation_statement`: the statements of reasons, by the member each is addressed to.
 *
 * **Identifiers compare byte by byte.** Members, targets and the identifiers of rows decide what a query reads — the
 * notes on a member, the reports of a target, the statements addressed to a member: given the platform, MySQL and
 * MariaDB store them in `utf8mb4` with the `utf8mb4_bin` collation, so that the notes on `Ann` are never read for `ann`,
 * as a server's default collation (`utf8mb4_0900_ai_ci`) would read them. PostgreSQL's default collations are
 * deterministic and SQLite compares bytes: their columns keep the default.
 */
final class ModerationTables
{
    public const string REPORTS = 'phil_moderation_report';

    public const string DECISIONS = 'phil_moderation_decision';

    public const string NOTES = 'phil_moderation_note';

    public const string SANDBOX = 'phil_moderation_sandbox';

    public const string STATEMENTS = 'phil_moderation_statement';

    private function __construct()
    {
    }

    /**
     * The schema with the five tables added, those it already has left as they are. Without a platform, the columns
     * keep the connection's default collation: the schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables($platform) as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    public static function tables(?AbstractPlatform $platform = null): array
    {
        $binary = $platform instanceof AbstractMySQLPlatform;

        return [
            Table::editor()
                ->setUnquotedName(self::REPORTS)
                ->addColumn(self::id('id', $binary))
                ->addColumn(self::column('target_type', Types::STRING, 64, true, $binary))
                ->addColumn(self::column('target_id', Types::STRING, TargetReference::MAX_ID_LENGTH, true, $binary))
                ->addColumn(self::column('source', Types::STRING, 16, true))
                ->addColumn(self::column('reason', Types::STRING, 64, true))
                ->addColumn(self::column('reporter', Types::STRING, MemberId::MAX_LENGTH, false, $binary))
                ->addColumn(self::column('comment', Types::TEXT, null, false))
                ->addColumn(self::column('reported_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
                ->addColumn(self::id('decision_id', $binary, false))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index('idx_phil_moderation_report_open', 'decision_id', 'reported_at'))
                ->addIndex(self::index('idx_phil_moderation_report_target', 'target_type', 'target_id'))
                ->addIndex(self::index('idx_phil_moderation_report_reporter', 'reporter'))
                ->addIndex(self::index('idx_phil_moderation_report_time', 'reported_at'))
                ->create(),
            Table::editor()
                ->setUnquotedName(self::DECISIONS)
                ->addColumn(self::id('id', $binary))
                ->addColumn(self::column('target_type', Types::STRING, 64, true, $binary))
                ->addColumn(self::column('target_id', Types::STRING, TargetReference::MAX_ID_LENGTH, true, $binary))
                ->addColumn(self::column('action', Types::STRING, 16, true))
                ->addColumn(self::column('moderator', Types::STRING, MemberId::MAX_LENGTH, false, $binary))
                ->addColumn(self::column('role', Types::STRING, 64, true))
                ->addColumn(self::column('public_reason', Types::TEXT, null, false))
                ->addColumn(self::column('note', Types::TEXT, null, false))
                ->addColumn(self::column('author', Types::STRING, MemberId::MAX_LENGTH, false, $binary))
                ->addColumn(self::column('decided_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
                ->addColumn(self::column('is_public', Types::BOOLEAN, null, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index('idx_phil_moderation_decision_target', 'target_type', 'target_id'))
                ->addIndex(self::index('idx_phil_moderation_decision_author', 'author'))
                ->addIndex(self::index('idx_phil_moderation_decision_moderator', 'moderator'))
                ->addIndex(self::index('idx_phil_moderation_decision_public', 'is_public', 'decided_at'))
                ->create(),
            Table::editor()
                ->setUnquotedName(self::NOTES)
                ->addColumn(self::id('id', $binary))
                ->addColumn(self::column('member', Types::STRING, MemberId::MAX_LENGTH, true, $binary))
                ->addColumn(self::column('author', Types::STRING, MemberId::MAX_LENGTH, false, $binary))
                ->addColumn(self::column('body', Types::TEXT, null, true))
                ->addColumn(self::column('written_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index('idx_phil_moderation_note_member', 'member', 'written_at'))
                ->addIndex(self::index('idx_phil_moderation_note_author', 'author'))
                ->create(),
            Table::editor()
                ->setUnquotedName(self::SANDBOX)
                ->addColumn(self::key('member', $binary))
                ->addColumn(self::column('cleared_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member')->create())
                ->create(),
            Table::editor()
                ->setUnquotedName(self::STATEMENTS)
                ->addColumn(self::id('id', $binary))
                ->addColumn(self::id('decision_id', $binary))
                ->addColumn(self::column('recipient', Types::STRING, MemberId::MAX_LENGTH, true, $binary))
                ->addColumn(self::column('target_type', Types::STRING, 64, true, $binary))
                ->addColumn(self::column('target_id', Types::STRING, TargetReference::MAX_ID_LENGTH, true, $binary))
                ->addColumn(self::column('action', Types::STRING, 16, true))
                ->addColumn(self::column('reason', Types::STRING, 64, true))
                ->addColumn(self::column('ground', Types::STRING, 16, true))
                ->addColumn(self::column('reference', Types::STRING, StatementOfReasons::MAX_REFERENCE_LENGTH, false))
                ->addColumn(self::column('explanation', Types::TEXT, null, true))
                ->addColumn(self::column('prompted', Types::BOOLEAN, null, true))
                ->addColumn(self::column('automated_detection', Types::BOOLEAN, null, true))
                ->addColumn(self::column('issued_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index('idx_phil_moderation_statement_recipient', 'recipient', 'issued_at'))
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName('uniq_phil_moderation_statement_decision')->setUnquotedColumnNames('decision_id')->create())
                ->create(),
        ];
    }

    /**
     * The member of a primary key, which no platform lets hold a null.
     *
     * @param non-empty-string $name
     */
    private static function key(string $name, bool $binary): Column
    {
        return self::column($name, Types::STRING, MemberId::MAX_LENGTH, true, $binary);
    }

    /**
     * @param non-empty-string $name
     */
    private static function id(string $name, bool $binary, bool $required = true): Column
    {
        return self::column($name, Types::STRING, Identifiers::LENGTH, $required, $binary)->edit()->setFixed(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, ?int $length, bool $required, bool $binary = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string ...$columns
     */
    private static function index(string $name, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames(...$columns)->create();
    }
}
