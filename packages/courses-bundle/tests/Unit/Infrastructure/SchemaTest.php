<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns of each table as the schema declares them — name, type, required, length, fixed length — their keys and
 * indexes, the listener that adds them to the schema of the ORM, and the upgrade of the tables of an earlier schema.
 */
#[CoversClass(CourseTables::class)]
#[CoversClass(SchemaListener::class)]
final class SchemaTest extends TestCase
{
    public function testEachColumnHasItsTypeItsLengthAndWhetherItIsRequired(): void
    {
        $tables = [];

        foreach (CourseTables::configure(Schema::editor()->create())->getTables() as $table) {
            $tables[$table->getObjectName()->toString()] = array_map(
                static fn (Column $column): string => \sprintf('%s:%s:%s:%s:%s', $column->getObjectName()->toString(), $column->getTypeName(), $column->getNotnull() ? '1' : '0', $column->getLength() ?? '', $column->getFixed() ? '1' : '0'),
                $table->getColumns(),
            );
        }

        self::assertSame([
            'phil_course' => ['id:string:1:36:0', 'title:string:1:255:0', 'description:text:1::0', 'origin:string:1:16:0', 'source:string:0:255:0', 'ordered:boolean:1::0', 'created_at:datetime_utc_immutable:1::0'],
            'phil_course_module' => ['id:string:1:36:0', 'course_id:string:1:36:0', 'title:string:1:255:0', 'position:integer:1::0', 'release_on:datetime_utc_immutable:0::0', 'release_days:integer:0::0'],
            'phil_course_block' => ['id:string:1:36:0', 'course_id:string:1:36:0', 'parent_id:string:0:36:0', 'title:string:1:255:0', 'position:integer:1::0', 'source:string:1:255:0'],
            'phil_course_activity' => ['id:string:1:36:0', 'course_id:string:1:36:0', 'module_id:string:1:36:0', 'title:string:1:255:0', 'type:string:1:64:0', 'position:integer:1::0', 'settings:text:1::0', 'requirement:string:1:32:0', 'required:boolean:1::0', 'max_attempts:integer:0::0', 'release_on:datetime_utc_immutable:0::0', 'release_days:integer:0::0', 'source:string:0:255:0', 'block_id:string:0:36:0'],
            'phil_course_enrollment' => ['id:string:1:36:0', 'course_id:string:1:36:0', 'learner:string:1:190:0', 'registration:string:1:36:0', 'enrolled_at:datetime_utc_immutable:1::0', 'completed_at:datetime_utc_immutable:0::0'],
            'phil_course_progress' => ['enrollment_id:string:1:36:0', 'activity_id:string:1:36:0', 'completed:boolean:1::0', 'success:boolean:0::0', 'waived:boolean:1::0', 'score:float:0::0', 'failures:integer:1::0', 'extra_tries:integer:1::0', 'started_at:datetime_utc_immutable:0::0', 'satisfied_at:datetime_utc_immutable:0::0', 'updated_at:datetime_utc_immutable:1::0'],
            'phil_course_satisfaction' => ['enrollment_id:string:1:36:0', 'subject_id:string:1:36:0', 'statement_id:string:1:36:0', 'satisfied_at:datetime_utc_immutable:1::0'],
            'phil_course_retry' => ['id:string:1:36:0', 'enrollment_id:string:1:36:0', 'activity_id:string:1:36:0', 'reason:text:1::0', 'decision:string:1:16:0', 'requested_at:datetime_utc_immutable:1::0', 'decided_at:datetime_utc_immutable:0::0', 'decided_by:string:0:190:0', 'note:text:0::0'],
            'phil_course_cmi5_session' => ['id:string:1:36:0', 'enrollment_id:string:1:36:0', 'activity_id:string:1:36:0', 'fetched:boolean:1::0', 'state:string:1:16:0', 'launched_at:datetime_utc_immutable:1::0', 'expires_at:datetime_utc_immutable:1::0', 'ended_at:datetime_utc_immutable:0::0'],
            'phil_course_statement' => ['id:string:1:36:0', 'registration:string:1:36:0', 'body:text:1::0', 'stored_at:datetime_utc_immutable:1::0'],
            'phil_course_state' => ['activity_hash:string:1:64:1', 'registration:string:1:36:0', 'state_id:string:1:190:0', 'document:text:1::0', 'updated_at:datetime_utc_immutable:1::0'],
            'phil_course_scorm_attempt' => ['id:string:1:36:0', 'enrollment_id:string:1:36:0', 'activity_id:string:1:36:0', 'number:integer:1::0', 'version:string:1:8:0', 'state:string:1:16:0', 'session:integer:1::0', 'completion:string:1:16:0', 'success:string:1:16:0', 'score:float:0::0', 'total_time:bigint:1::0', 'started_at:datetime_utc_immutable:1::0', 'updated_at:datetime_utc_immutable:1::0', 'ended_at:datetime_utc_immutable:0::0', 'elapsed:integer:0::0', 'session_opened_at:datetime_utc_immutable:0::0'],
            'phil_course_scorm_data' => ['attempt_id:string:1:36:0', 'data:text:1:16777215:0', 'updated_at:datetime_utc_immutable:1::0'],
            'phil_course_assignment' => ['id:string:1:36:0', 'course_id:string:1:36:0', 'kind:string:1:16:0', 'assignee:string:1:190:0', 'due_at:datetime_utc_immutable:1::0', 'renew_months:integer:0::0', 'assigned_by:string:0:190:0', 'assigned_at:datetime_utc_immutable:1::0'],
            'phil_course_obligation' => ['assignment_id:string:1:36:0', 'learner:string:1:190:0', 'course_id:string:1:36:0', 'cycle:integer:1::0', 'due_at:datetime_utc_immutable:1::0', 'assigned_at:datetime_utc_immutable:1::0', 'completed_at:datetime_utc_immutable:0::0', 'renews_at:datetime_utc_immutable:0::0', 'reminders:string:1:64:0'],
            'phil_course_transcript' => ['id:string:1:36:0', 'learner:string:1:190:0', 'course_id:string:1:36:0', 'course_title:string:1:255:0', 'completed_at:datetime_utc_immutable:1::0', 'credit_hundredths:integer:0::0', 'credit_unit:string:0:16:0', 'assignment_id:string:0:36:0', 'cycle:integer:0::0'],
            'phil_course_credits' => ['course_id:string:1:36:0', 'hundredths:integer:1::0', 'unit:string:1:16:0'],
            'phil_course_path' => ['id:string:1:36:0', 'title:string:1:255:0', 'description:text:1::0', 'created_at:datetime_utc_immutable:1::0'],
            'phil_course_path_step' => ['path_id:string:1:36:0', 'course_id:string:1:36:0', 'position:integer:1::0', 'required:boolean:1::0', 'prerequisites:text:1::0'],
            'phil_course_path_enrollment' => ['id:string:1:36:0', 'path_id:string:1:36:0', 'learner:string:1:190:0', 'enrolled_at:datetime_utc_immutable:1::0', 'completed_at:datetime_utc_immutable:0::0'],
            'phil_course_equivalence' => ['id:string:1:36:0', 'title:string:1:255:0', 'created_at:datetime_utc_immutable:1::0'],
            'phil_course_equivalent' => ['course_id:string:1:36:0', 'group_id:string:1:36:0', 'language:string:1:35:0'],
            'phil_course_catalogue' => ['id:string:1:36:0', 'title:string:1:255:0', 'description:text:1::0', 'audience:string:1:16:0', 'unit:string:0:128:0', 'created_at:datetime_utc_immutable:1::0'],
            'phil_course_catalogue_entry' => ['catalogue_id:string:1:36:0', 'course_id:string:1:36:0', 'position:integer:1::0', 'self_enrollment:boolean:1::0', 'price_minor:bigint:0::0', 'price_currency:string:0:3:0', 'tags:text:1::0'],
        ], $tables);
        self::assertSame(array_reverse(array_keys($tables)), CourseTables::ALL);
    }

    public function testTheKeysAndIndexesServeTheQueries(): void
    {
        $schema = CourseTables::configure(Schema::editor()->create());
        $indexes = static fn (Table $table): array => array_values(array_map(static fn (Index $index): string => $index->getObjectName()->toString().'('.implode(',', array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns())).')', array_filter($table->getIndexes(), static fn (Index $index): bool => 'primary' !== $index->getObjectName()->toString())));

        self::assertSame(['idx_phil_course_created(created_at)'], $indexes($schema->getTable(CourseTables::COURSE)));
        self::assertSame(['idx_phil_course_module_course(course_id,position)'], $indexes($schema->getTable(CourseTables::MODULE)));
        self::assertSame(['idx_phil_course_block_course(course_id,parent_id,position)'], $indexes($schema->getTable(CourseTables::BLOCK)));
        self::assertSame(['idx_phil_course_activity_course(course_id,module_id,position)'], $indexes($schema->getTable(CourseTables::ACTIVITY)));
        self::assertSame(['idx_phil_course_enrollment_learner(learner)'], $indexes($schema->getTable(CourseTables::ENROLLMENT)));
        self::assertSame(['idx_phil_course_retry_pending(decision,requested_at)', 'idx_phil_course_retry_enrollment(enrollment_id,activity_id)'], $indexes($schema->getTable(CourseTables::RETRY)));
        self::assertSame(['idx_phil_course_cmi5_session_open(enrollment_id,activity_id,state)'], $indexes($schema->getTable(CourseTables::SESSION)));
        self::assertSame(['idx_phil_course_statement_registration(registration,stored_at)'], $indexes($schema->getTable(CourseTables::STATEMENT)));
        self::assertCount(2, $schema->getTable(CourseTables::ENROLLMENT)->getUniqueConstraints());
        self::assertSame(['enrollment_id', 'activity_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::PROGRESS)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['enrollment_id', 'subject_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::SATISFACTION)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['activity_hash', 'registration', 'state_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::STATE)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame([], $indexes($schema->getTable(CourseTables::SCORM_ATTEMPT)));
        self::assertSame(['enrollment_id', 'activity_id', 'number'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), array_first($schema->getTable(CourseTables::SCORM_ATTEMPT)->getUniqueConstraints())?->getColumnNames() ?? []));
        self::assertSame(['attempt_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::SCORM_DATA)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['idx_phil_course_path_created(created_at)'], $indexes($schema->getTable(CourseTables::PATH)));
        self::assertSame(['idx_phil_course_path_step_course(course_id)'], $indexes($schema->getTable(CourseTables::PATH_STEP)));
        self::assertSame(['path_id', 'course_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::PATH_STEP)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['idx_phil_course_path_enrollment_learner(learner,completed_at)'], $indexes($schema->getTable(CourseTables::PATH_ENROLLMENT)));
        self::assertSame(['path_id', 'learner'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), array_first($schema->getTable(CourseTables::PATH_ENROLLMENT)->getUniqueConstraints())?->getColumnNames() ?? []));
        self::assertSame(['idx_phil_course_equivalence_title(title)'], $indexes($schema->getTable(CourseTables::EQUIVALENCE)));
        self::assertSame([], $indexes($schema->getTable(CourseTables::EQUIVALENT)));
        self::assertSame(['course_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::EQUIVALENT)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['group_id', 'language'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), array_first($schema->getTable(CourseTables::EQUIVALENT)->getUniqueConstraints())?->getColumnNames() ?? []));
        self::assertSame(['idx_phil_course_catalogue_audience(audience,unit)', 'idx_phil_course_catalogue_title(title)'], $indexes($schema->getTable(CourseTables::CATALOGUE)));
        self::assertSame(['idx_phil_course_catalogue_entry_course(course_id)'], $indexes($schema->getTable(CourseTables::CATALOGUE_ENTRY)));
        self::assertSame(['catalogue_id', 'course_id'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(CourseTables::CATALOGUE_ENTRY)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
    }

    public function testTheKeysOfTheScormTablesCompareBytesOnMySql(): void
    {
        $collations = static fn (?AbstractPlatform $platform): array => array_map(
            static fn (Column $column): ?string => $column->getCollation(),
            array_filter(CourseTables::configure(Schema::editor()->create(), $platform)->getTable(CourseTables::SCORM_ATTEMPT)->getColumns(), static fn (Column $column): bool => str_ends_with($column->getObjectName()->toString(), 'id')),
        );

        self::assertSame(['utf8mb4_bin', 'utf8mb4_bin', 'utf8mb4_bin'], array_values($collations(new MySQLPlatform())));
        self::assertSame('utf8mb4', CourseTables::configure(Schema::editor()->create(), new MySQLPlatform())->getTable(CourseTables::SCORM_DATA)->getColumn('attempt_id')->getCharset());
        self::assertSame([null, null, null], array_values($collations(new PostgreSQLPlatform())));
        self::assertSame([null, null, null], array_values($collations(null)));
        self::assertNull(CourseTables::configure(Schema::editor()->create(), new MySQLPlatform())->getTable(CourseTables::ENROLLMENT)->getColumn('id')->getCollation());
    }

    public function testATableTheSchemaHasIsLeftAsItIs(): void
    {
        $schema = Schema::editor()->addTable(Table::editor()->setUnquotedName(CourseTables::COURSE)->addColumn(Column::editor()->setUnquotedName('own')->setTypeName('integer')->create())->create())->create();

        self::assertSame(['own'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), CourseTables::configure($schema)->getTable(CourseTables::COURSE)->getColumns()));
        self::assertCount(24, CourseTables::configure($schema)->getTables());
    }

    public function testTheListenerAddsTheTablesToItsConnectionOnly(): void
    {
        $connection = Store::sqlite();
        $other = Store::sqlite();
        $listener = new SchemaListener($connection);

        foreach ([[$connection, 24], [$other, 0]] as [$used, $count]) {
            $manager = self::createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($used);
            $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
            $listener->postGenerateSchema($event);
            self::assertCount($count, $event->getSchema()->getTables());
        }
    }

    public function testTheTablesOfAnEarlierSchemaAreBroughtUpToDate(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        Store::createEarlier($connection);
        $connection->insert(CourseTables::COURSE, ['id' => 'c1', 'title' => 'Knots', 'description' => '', 'origin' => 'native', 'ordered' => 0, 'created_at' => '2026-11-13 10:00:00']);
        $connection->insert(CourseTables::MODULE, ['id' => 'm1', 'course_id' => 'c1', 'title' => 'Basics', 'position' => 0]);
        $connection->insert(CourseTables::ACTIVITY, ['id' => 'a1', 'course_id' => 'c1', 'module_id' => 'm1', 'title' => 'Reef', 'type' => 'page', 'position' => 0, 'settings' => '{}', 'requirement' => 'Completed', 'required' => 1]);

        $upgrade = CourseTables::upgrade($connection);
        self::assertNotSame([], $upgrade);

        foreach ($upgrade as $sql) {
            $connection->executeStatement($sql);
        }

        self::assertSame([], CourseTables::upgrade($connection));
        $manager = $connection->createSchemaManager();
        self::assertTrue($manager->tablesExist([CourseTables::BLOCK, CourseTables::SATISFACTION, CourseTables::SCORM_ATTEMPT, CourseTables::SCORM_DATA]));
        self::assertTrue($manager->introspectTableByUnquotedName(CourseTables::ACTIVITY)->hasColumn('block_id'));
        self::assertTrue($manager->introspectTableByUnquotedName(CourseTables::RETRY)->hasColumn('note'));
        self::assertTrue($manager->introspectTableByUnquotedName(CourseTables::RETRY)->hasIndex('idx_phil_course_retry_pending'));
        self::assertSame('Reef', new DbalCourseRepository($connection)->activity(ActivityId::of('a1'))?->title);
    }
}
