<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The bundle's tables, their instants in `datetime_utc_immutable` columns:
 *
 * - the questionnaires, by key, as the data of their JSON schema, with their retention apart for the purge;
 * - the responses: no column for an IP address, a respondent only when the questionnaire is not anonymous;
 * - the addenda of the responses, in order;
 * - the atomic counters of the quotas, one per questionnaire and quota;
 * - the marks of participation: the questionnaire, the subject and the keyed digest of the respondent, with no time and
 *   no reference to a response.
 *
 * On MySQL and MariaDB the keys and identifiers are stored with the `utf8mb4_bin` collation, so that they compare byte
 * by byte, as PostgreSQL and SQLite compare them.
 */
final class SurveyTables
{
    public const string QUESTIONNAIRES = 'phil_survey_questionnaires';

    public const string RESPONSES = 'phil_survey_responses';

    public const string ADDENDA = 'phil_survey_addenda';

    public const string QUOTAS = 'phil_survey_quota_counts';

    public const string PARTICIPATIONS = 'phil_survey_participations';

    public const array ALL = [self::QUESTIONNAIRES, self::RESPONSES, self::ADDENDA, self::QUOTAS, self::PARTICIPATIONS];

    private const int KEY = 64;

    private const int ID = 191;

    private function __construct()
    {
    }

    /**
     * Adds the tables the schema lacks.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;
        $editor = $schema->edit();

        foreach (self::tables($binary) as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return array<string, Table>
     */
    private static function tables(bool $binary): array
    {
        return [
            self::QUESTIONNAIRES => self::table(self::QUESTIONNAIRES, [
                self::string('questionnaire_key', self::KEY, $binary),
                self::text('definition'),
                Column::editor()->setUnquotedName('retention_days')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                self::instant('updated_at'),
            ], ['questionnaire_key']),
            self::RESPONSES => self::table(self::RESPONSES, [
                self::string('id', 36, $binary),
                self::string('questionnaire', self::KEY, $binary),
                self::string('subject', self::ID, $binary, true),
                self::string('respondent', self::ID, $binary, true),
                self::string('ending', self::KEY, $binary),
                self::text('answers'),
                self::text('computed'),
                self::string('addendum_digest', 64, $binary, true),
                self::instant('submitted_at'),
            ], ['id'], ['subject' => ['questionnaire', 'subject'], 'submitted' => ['questionnaire', 'submitted_at'], 'respondent' => ['respondent']], ['addendum' => ['addendum_digest']]),
            self::ADDENDA => self::table(self::ADDENDA, [
                self::string('response_id', 36, $binary),
                Column::editor()->setUnquotedName('position')->setTypeName(Types::SMALLINT)->create(),
                self::text('body'),
                self::instant('added_at'),
            ], ['response_id', 'position']),
            self::QUOTAS => self::table(self::QUOTAS, [
                self::string('questionnaire', self::KEY, $binary),
                self::string('quota', self::KEY, $binary),
                Column::editor()->setUnquotedName('taken')->setTypeName(Types::INTEGER)->create(),
            ], ['questionnaire', 'quota']),
            self::PARTICIPATIONS => self::table(self::PARTICIPATIONS, [
                self::string('questionnaire', self::KEY, $binary),
                self::string('subject', self::ID, $binary),
                self::string('respondent_digest', 64, $binary),
            ], ['questionnaire', 'subject', 'respondent_digest'], ['respondent' => ['respondent_digest']]),
        ];
    }

    /**
     * @param non-empty-string                                $name
     * @param list<Column>                                    $columns
     * @param non-empty-list<non-empty-string>                $primary
     * @param array<non-empty-string, list<non-empty-string>> $indexes
     * @param array<non-empty-string, list<non-empty-string>> $uniques
     */
    private static function table(string $name, array $columns, array $primary, array $indexes = [], array $uniques = []): Table
    {
        $built = [];

        foreach ($indexes as $suffix => $indexed) {
            $built[] = Index::editor()->setUnquotedName($name.'_'.$suffix)->setUnquotedColumnNames(...$indexed)->create();
        }

        foreach ($uniques as $suffix => $indexed) {
            $built[] = Index::editor()->setUnquotedName($name.'_'.$suffix)->setUnquotedColumnNames(...$indexed)->setType(IndexType::UNIQUE)->create();
        }

        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primary)->create())
            ->setIndexes(...$built)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary, bool $nullable = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function instant(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->create();
    }
}
