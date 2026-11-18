<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The tables of the courses: `phil_course`, `phil_course_module` and `phil_course_activity`, the course and its
 * outline; `phil_course_block`, the blocks of a cmi5 course structure; `phil_course_enrollment` and
 * `phil_course_progress`, the learners and where they stand; `phil_course_satisfaction`, the blocks and courses whose
 * `satisfied` statement an enrollment has; `phil_course_retry`, the requests for a second chance; `phil_course_cmi5_session`, the cmi5 launches; `phil_course_statement` and
 * `phil_course_state`, the statements and state documents of the store inside the bundle. Moments are kept in UTC.
 */
final class CourseTables
{
    public const string COURSE = 'phil_course';

    public const string MODULE = 'phil_course_module';

    public const string BLOCK = 'phil_course_block';

    public const string ACTIVITY = 'phil_course_activity';

    public const string ENROLLMENT = 'phil_course_enrollment';

    public const string PROGRESS = 'phil_course_progress';

    public const string SATISFACTION = 'phil_course_satisfaction';

    public const string RETRY = 'phil_course_retry';

    public const string SESSION = 'phil_course_cmi5_session';

    public const string STATEMENT = 'phil_course_statement';

    public const string STATE = 'phil_course_state';

    /** Every table, the ones that point to others first. */
    public const array ALL = [self::STATE, self::STATEMENT, self::SESSION, self::RETRY, self::SATISFACTION, self::PROGRESS, self::ENROLLMENT, self::ACTIVITY, self::BLOCK, self::MODULE, self::COURSE];

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, each left as it is when the schema already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables() as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * The statements that bring the tables of a connection up to date: the tables it lacks created, the columns and
     * indexes a table lacks added — nullable, so the rows already kept stay valid —, nothing changed or dropped. Run
     * once more, they are none. With the ORM, `doctrine:migrations:diff` writes the same changes.
     *
     * @return list<string>
     */
    public static function upgrade(Connection $connection): array
    {
        $manager = $connection->createSchemaManager();
        $current = [];
        $target = [];

        foreach (self::tables() as $name => $table) {
            if (!$manager->tablesExist([$name])) {
                $target[] = $table;

                continue;
            }

            $existing = $manager->introspectTableByUnquotedName($name);
            $current[] = $existing;
            $editor = $existing->edit();

            foreach ($table->getColumns() as $column) {
                if (!$existing->hasColumn($column->getObjectName()->toString())) {
                    $editor->addColumn($column);
                }
            }

            foreach ($table->getIndexes() as $index) {
                $indexName = $index->getObjectName()->toString();

                if ('primary' !== $indexName && !$existing->hasIndex($indexName)) {
                    $editor->addIndex($index);
                }
            }

            $target[] = $editor->create();
        }

        $diff = $manager->createComparator()->compareSchemas(Schema::editor()->setTables(...$current)->create(), Schema::editor()->setTables(...$target)->create());

        return $connection->getDatabasePlatform()->getAlterSchemaSQL($diff);
    }

    /**
     * @return array<non-empty-string, Table>
     */
    private static function tables(): array
    {
        return [
            self::COURSE => Table::editor()->setUnquotedName(self::COURSE)
                ->addColumn(self::id('id'))
                ->addColumn(self::column('title', Types::STRING, true, 255))
                ->addColumn(self::column('description', Types::TEXT, true))
                ->addColumn(self::column('origin', Types::STRING, true, 16))
                ->addColumn(self::column('source', Types::STRING, false, Course::SOURCE_LENGTH))
                ->addColumn(self::column('ordered', Types::BOOLEAN, true))
                ->addColumn(self::column('created_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_created', 'created_at'))
                ->create(),
            self::MODULE => Table::editor()->setUnquotedName(self::MODULE)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('course_id'))
                ->addColumn(self::column('title', Types::STRING, true, 255))
                ->addColumn(self::column('position', Types::INTEGER, true))
                ->addColumn(self::column('release_on', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('release_days', Types::INTEGER, false))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_module_course', 'course_id', 'position'))
                ->create(),
            self::BLOCK => Table::editor()->setUnquotedName(self::BLOCK)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('course_id'))
                ->addColumn(self::column('parent_id', Types::STRING, false, 36))
                ->addColumn(self::column('title', Types::STRING, true, 255))
                ->addColumn(self::column('position', Types::INTEGER, true))
                ->addColumn(self::column('source', Types::STRING, true, Course::SOURCE_LENGTH))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_block_course', 'course_id', 'parent_id', 'position'))
                ->create(),
            self::ACTIVITY => Table::editor()->setUnquotedName(self::ACTIVITY)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('course_id'))
                ->addColumn(self::id('module_id'))
                ->addColumn(self::column('title', Types::STRING, true, 255))
                ->addColumn(self::column('type', Types::STRING, true, 64))
                ->addColumn(self::column('position', Types::INTEGER, true))
                ->addColumn(self::column('settings', Types::TEXT, true))
                ->addColumn(self::column('requirement', Types::STRING, true, 32))
                ->addColumn(self::column('required', Types::BOOLEAN, true))
                ->addColumn(self::column('max_attempts', Types::INTEGER, false))
                ->addColumn(self::column('release_on', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('release_days', Types::INTEGER, false))
                ->addColumn(self::column('source', Types::STRING, false, Course::SOURCE_LENGTH))
                ->addColumn(self::column('block_id', Types::STRING, false, 36))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_activity_course', 'course_id', 'module_id', 'position'))
                ->create(),
            self::ENROLLMENT => Table::editor()->setUnquotedName(self::ENROLLMENT)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('course_id'))
                ->addColumn(self::column('learner', Types::STRING, true, LearnerId::MAX_LENGTH))
                ->addColumn(self::id('registration'))
                ->addColumn(self::column('enrolled_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('completed_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addUniqueConstraint(self::unique('uniq_phil_course_enrollment', 'course_id', 'learner'))
                ->addUniqueConstraint(self::unique('uniq_phil_course_registration', 'registration'))
                ->addIndex(self::index('idx_phil_course_enrollment_learner', 'learner'))
                ->create(),
            self::PROGRESS => Table::editor()->setUnquotedName(self::PROGRESS)
                ->addColumn(self::id('enrollment_id'))
                ->addColumn(self::id('activity_id'))
                ->addColumn(self::column('completed', Types::BOOLEAN, true))
                ->addColumn(self::column('success', Types::BOOLEAN, false))
                ->addColumn(self::column('waived', Types::BOOLEAN, true))
                ->addColumn(self::column('score', Types::FLOAT, false))
                ->addColumn(self::column('failures', Types::INTEGER, true))
                ->addColumn(self::column('extra_tries', Types::INTEGER, true))
                ->addColumn(self::column('started_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('satisfied_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('updated_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(self::primary('enrollment_id', 'activity_id'))
                ->create(),
            self::SATISFACTION => Table::editor()->setUnquotedName(self::SATISFACTION)
                ->addColumn(self::id('enrollment_id'))
                ->addColumn(self::id('subject_id'))
                ->addColumn(self::id('statement_id'))
                ->addColumn(self::column('satisfied_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(self::primary('enrollment_id', 'subject_id'))
                ->create(),
            self::RETRY => Table::editor()->setUnquotedName(self::RETRY)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('enrollment_id'))
                ->addColumn(self::id('activity_id'))
                ->addColumn(self::column('reason', Types::TEXT, true))
                ->addColumn(self::column('decision', Types::STRING, true, 16))
                ->addColumn(self::column('requested_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('decided_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addColumn(self::column('decided_by', Types::STRING, false, 190))
                ->addColumn(self::column('note', Types::TEXT, false))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_retry_pending', 'decision', 'requested_at'))
                ->addIndex(self::index('idx_phil_course_retry_enrollment', 'enrollment_id', 'activity_id'))
                ->create(),
            self::SESSION => Table::editor()->setUnquotedName(self::SESSION)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('enrollment_id'))
                ->addColumn(self::id('activity_id'))
                ->addColumn(self::column('fetched', Types::BOOLEAN, true))
                ->addColumn(self::column('state', Types::STRING, true, 16))
                ->addColumn(self::column('launched_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('expires_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('ended_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_cmi5_session_open', 'enrollment_id', 'activity_id', 'state'))
                ->create(),
            self::STATEMENT => Table::editor()->setUnquotedName(self::STATEMENT)
                ->addColumn(self::id('id'))
                ->addColumn(self::id('registration'))
                ->addColumn(self::column('body', Types::TEXT, true))
                ->addColumn(self::column('stored_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('idx_phil_course_statement_registration', 'registration', 'stored_at'))
                ->create(),
            self::STATE => Table::editor()->setUnquotedName(self::STATE)
                ->addColumn(self::column('activity_hash', Types::STRING, true, 64)->edit()->setFixed(true)->create())
                ->addColumn(self::id('registration'))
                ->addColumn(self::column('state_id', Types::STRING, true, 190))
                ->addColumn(self::column('document', Types::TEXT, true))
                ->addColumn(self::column('updated_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(self::primary('activity_hash', 'registration', 'state_id'))
                ->create(),
        ];
    }

    /**
     * @param non-empty-string $name
     */
    private static function id(string $name): Column
    {
        return self::column($name, Types::STRING, true, 36);
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $required, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }

    /**
     * @param non-empty-string $column
     * @param non-empty-string ...$columns
     */
    private static function primary(string $column, string ...$columns): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames($column, ...$columns)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $column
     * @param non-empty-string ...$columns
     */
    private static function index(string $name, string $column, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames($column, ...$columns)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $column
     * @param non-empty-string ...$columns
     */
    private static function unique(string $name, string $column, string ...$columns): UniqueConstraint
    {
        return UniqueConstraint::editor()->setUnquotedName($name)->setUnquotedColumnNames($column, ...$columns)->create();
    }
}
