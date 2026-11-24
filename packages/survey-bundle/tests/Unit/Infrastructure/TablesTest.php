<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column as DbalColumn;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Infrastructure\Doctrine\Column;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveySchemaListener;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveyTables;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SurveyTables::class)]
#[CoversClass(SurveySchemaListener::class)]
#[CoversClass(Column::class)]
final class TablesTest extends TestCase
{
    public function testNoColumnOfAResponseOrAMarkCanNameItsRespondentOnItsOwn(): void
    {
        $schema = SurveyTables::configure(Schema::editor()->create());
        $columns = static fn (string $table): array => array_map(static fn (DbalColumn $column): string => $column->getObjectName()->toString(), $schema->getTable($table)->getColumns());

        self::assertSame(['id', 'questionnaire', 'subject', 'respondent', 'ending', 'answers', 'computed', 'addendum_digest', 'submitted_at'], $columns(SurveyTables::RESPONSES));
        self::assertSame(['questionnaire', 'subject', 'respondent_digest'], $columns(SurveyTables::PARTICIPATIONS));
        self::assertSame(['response_id', 'position', 'body', 'added_at'], $columns(SurveyTables::ADDENDA));
        self::assertSame(['questionnaire', 'quota', 'taken'], $columns(SurveyTables::QUOTAS));
        self::assertSame(['questionnaire_key', 'definition', 'retention_days', 'updated_at'], $columns(SurveyTables::QUESTIONNAIRES));

        foreach (SurveyTables::ALL as $table) {
            foreach ($columns($table) as $column) {
                self::assertDoesNotMatchRegularExpression('/\b(ip|address|agent|user|member|email)\b/i', $column, $table.'.'.$column);
            }
        }

        self::assertFalse($schema->getTable(SurveyTables::RESPONSES)->getColumn('respondent')->getNotnull());
        self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $schema->getTable(SurveyTables::RESPONSES)->getColumn('submitted_at')->getTypeName());
    }

    public function testTheKeysCompareByteByByteOnMysql(): void
    {
        $mysql = SurveyTables::configure(Schema::editor()->create(), new MySQLPlatform());
        $other = SurveyTables::configure(Schema::editor()->create());

        self::assertSame('utf8mb4_bin', $mysql->getTable(SurveyTables::RESPONSES)->getColumn('questionnaire')->getCollation());
        self::assertNull($mysql->getTable(SurveyTables::RESPONSES)->getColumn('answers')->getCollation());
        self::assertNull($other->getTable(SurveyTables::RESPONSES)->getColumn('questionnaire')->getCollation());
    }

    public function testATableTheSchemaHasIsLeft(): void
    {
        $schema = SurveyTables::configure(Schema::editor()->create());
        $again = SurveyTables::configure($schema);

        self::assertCount(5, $again->getTables());
    }

    public function testTheListenerAddsTheTablesToTheSchemaOfItsConnectionOnly(): void
    {
        $stage = new Stage();
        $listener = new SurveySchemaListener($stage->connection);
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($stage->connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(SurveyTables::RESPONSES));

        $elsewhere = self::createStub(EntityManagerInterface::class);
        $elsewhere->method('getConnection')->willReturn(new Stage()->connection);
        $untouched = new GenerateSchemaEventArgs($elsewhere, Schema::editor()->create());
        $listener->postGenerateSchema($untouched);
        self::assertFalse($untouched->getSchema()->hasTable(SurveyTables::RESPONSES));
    }

    public function testAColumnIsReadByItsType(): void
    {
        self::assertSame('a', Column::string('a'));
        self::assertSame('3', Column::string(3));
        self::assertNull(Column::nullableString(null));
        self::assertSame('x', Column::nullableString('x'));
        self::assertSame(3, Column::int(3));
        self::assertSame(-12, Column::int('-12'));

        $refused = 0;

        foreach ([static fn (): string => Column::string(1.5), static fn (): int => Column::int('1.5'), static fn (): int => Column::int(null), static fn (): int => Column::int('12a'), static fn (): int => Column::int("12\n"), static fn (): int => Column::int('1234567890123456789')] as $read) {
            try {
                $read();
            } catch (InvalidQuestionnaire) {
                ++$refused;
            }
        }

        self::assertSame(6, $refused);
        self::assertSame(123_456_789_012_345_678, Column::int('123456789012345678'));
    }

    public function testEachTableIsMadeOfTheseColumnsKeysAndIndexes(): void
    {
        $schema = SurveyTables::configure(Schema::editor()->create());
        $described = [];

        foreach ($schema->getTables() as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                $columns[$column->getObjectName()->toString()] = $column->getTypeName().(null === $column->getLength() ? '' : '('.$column->getLength().')').($column->getNotnull() ? '' : ' null');
            }

            $indexes = [];

            foreach ($table->getIndexes() as $index) {
                $indexes[$index->getObjectName()->toString()] = (IndexType::UNIQUE === $index->getType() ? 'unique ' : '').implode(',', array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns()));
            }

            ksort($indexes);
            $described[$table->getObjectName()->toString()] = ['columns' => $columns, 'indexes' => $indexes];
        }

        self::assertSame([
            'phil_survey_questionnaires' => [
                'columns' => ['questionnaire_key' => 'string(64)', 'definition' => 'text', 'retention_days' => 'integer null', 'updated_at' => 'datetime_utc_immutable'],
                'indexes' => ['primary' => 'unique questionnaire_key'],
            ],
            'phil_survey_responses' => [
                'columns' => ['id' => 'string(36)', 'questionnaire' => 'string(64)', 'subject' => 'string(191) null', 'respondent' => 'string(191) null', 'ending' => 'string(64)', 'answers' => 'text', 'computed' => 'text', 'addendum_digest' => 'string(64) null', 'submitted_at' => 'datetime_utc_immutable'],
                'indexes' => ['phil_survey_responses_addendum' => 'unique addendum_digest', 'phil_survey_responses_respondent' => 'respondent', 'phil_survey_responses_subject' => 'questionnaire,subject', 'phil_survey_responses_submitted' => 'questionnaire,submitted_at', 'primary' => 'unique id'],
            ],
            'phil_survey_addenda' => [
                'columns' => ['response_id' => 'string(36)', 'position' => 'smallint', 'body' => 'text', 'added_at' => 'datetime_utc_immutable'],
                'indexes' => ['primary' => 'unique response_id,position'],
            ],
            'phil_survey_quota_counts' => [
                'columns' => ['questionnaire' => 'string(64)', 'quota' => 'string(64)', 'taken' => 'integer'],
                'indexes' => ['primary' => 'unique questionnaire,quota'],
            ],
            'phil_survey_participations' => [
                'columns' => ['questionnaire' => 'string(64)', 'subject' => 'string(191)', 'respondent_digest' => 'string(64)'],
                'indexes' => ['phil_survey_participations_respondent' => 'respondent_digest', 'primary' => 'unique questionnaire,subject,respondent_digest'],
            ],
        ], $described);
    }
}
