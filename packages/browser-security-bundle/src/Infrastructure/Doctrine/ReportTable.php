<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\BrowserSecurityBundle\Application\ReportParser;

/**
 * The table of the violations, `phil_csp_report`: one row per fingerprint, with its count and its first and last time.
 */
final class ReportTable
{
    public const string NAME = 'phil_csp_report';

    private function __construct()
    {
    }

    /**
     * The schema with the table added, left as it is when it already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('fingerprint')->setTypeName(Types::STRING)->setLength(64)->setFixed(true)->setNotNull(true)->create())
            ->addColumn(self::string('document_uri', ReportParser::MAX_URI_LENGTH, true))
            ->addColumn(self::string('blocked_uri', ReportParser::MAX_URI_LENGTH, true))
            ->addColumn(self::string('effective_directive', 64, true))
            ->addColumn(self::string('disposition', 8, true))
            ->addColumn(self::string('source_file', ReportParser::MAX_URI_LENGTH, false))
            ->addColumn(self::integer('line_number', Types::INTEGER, false))
            ->addColumn(self::integer('column_number', Types::INTEGER, false))
            ->addColumn(self::integer('status_code', Types::INTEGER, false))
            ->addColumn(self::string('sample', ReportParser::MAX_SAMPLE_LENGTH * 4, false))
            ->addColumn(self::string('client', 64, false))
            ->addColumn(self::integer('hits', Types::BIGINT, true))
            ->addColumn(Column::editor()->setUnquotedName('first_seen_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('last_seen_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(Index::editor()->setUnquotedName('uniq_phil_csp_report_fingerprint')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('fingerprint')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_csp_report_seen')->setUnquotedColumnNames('last_seen_at')->create())
            ->create())->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $required): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull($required)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name, string $type, bool $required): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setNotNull($required)->create();
    }
}
