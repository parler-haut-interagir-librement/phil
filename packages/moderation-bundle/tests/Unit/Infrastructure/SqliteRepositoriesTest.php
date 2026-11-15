<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\Doctrine\Rows;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalReportRepository::class)]
#[CoversClass(DbalDecisionRepository::class)]
#[CoversClass(DbalMemberNoteRepository::class)]
#[CoversClass(DbalSandboxRepository::class)]
#[CoversClass(DbalStatementRepository::class)]
#[CoversClass(ModerationTables::class)]
#[CoversClass(Rows::class)]
final class SqliteRepositoriesTest extends RepositoriesTestCase
{
    public function testTheTables(): void
    {
        $schema = ModerationTables::configure(Schema::editor()->create());

        foreach ([ModerationTables::REPORTS, ModerationTables::DECISIONS, ModerationTables::NOTES, ModerationTables::SANDBOX, ModerationTables::STATEMENTS] as $name) {
            self::assertTrue($schema->hasTable($name));
        }

        self::assertSame($schema->getTable(ModerationTables::REPORTS), ModerationTables::configure($schema)->getTable(ModerationTables::REPORTS));
        $reports = $schema->getTable(ModerationTables::REPORTS);
        self::assertTrue($reports->getColumn('id')->getFixed());
        self::assertSame(32, $reports->getColumn('decision_id')->getLength());
        self::assertFalse($reports->getColumn('decision_id')->getNotnull());
        self::assertSame(190, $reports->getColumn('target_id')->getLength());
        self::assertSame(190, $reports->getColumn('reporter')->getLength());
        self::assertSame(64, $reports->getColumn('target_type')->getLength());
        self::assertSame(16, $reports->getColumn('source')->getLength());
        self::assertTrue($reports->hasIndex('idx_phil_moderation_report_open'));
        self::assertTrue($reports->hasIndex('idx_phil_moderation_report_target'));
        self::assertTrue($reports->hasIndex('idx_phil_moderation_report_reporter'));
        self::assertTrue($reports->hasIndex('idx_phil_moderation_report_time'));
        $decisions = $schema->getTable(ModerationTables::DECISIONS);
        self::assertTrue($decisions->hasIndex('idx_phil_moderation_decision_public'));
        self::assertTrue($decisions->hasIndex('idx_phil_moderation_decision_author'));
        self::assertTrue($decisions->hasIndex('idx_phil_moderation_decision_moderator'));
        self::assertTrue($decisions->hasIndex('idx_phil_moderation_decision_target'));
        self::assertTrue($decisions->getColumn('is_public')->getNotnull());
        self::assertTrue($schema->getTable(ModerationTables::NOTES)->hasIndex('idx_phil_moderation_note_member'));
        self::assertTrue($schema->getTable(ModerationTables::NOTES)->hasIndex('idx_phil_moderation_note_author'));

        // Every column: its length (null when it has none) and whether it must be given.
        $columns = [
            ModerationTables::REPORTS => [
                'id' => [32, true], 'target_type' => [64, true], 'target_id' => [190, true], 'source' => [16, true], 'reason' => [64, true],
                'reporter' => [190, false], 'comment' => [null, false], 'reported_at' => [null, true], 'decision_id' => [32, false],
            ],
            ModerationTables::DECISIONS => [
                'id' => [32, true], 'target_type' => [64, true], 'target_id' => [190, true], 'action' => [16, true], 'moderator' => [190, false],
                'role' => [64, true], 'public_reason' => [null, false], 'note' => [null, false], 'author' => [190, false],
                'decided_at' => [null, true], 'is_public' => [null, true],
            ],
            ModerationTables::NOTES => [
                'id' => [32, true], 'member' => [190, true], 'author' => [190, false], 'body' => [null, true], 'written_at' => [null, true],
            ],
            ModerationTables::SANDBOX => ['member' => [190, true], 'cleared_at' => [null, true]],
            ModerationTables::STATEMENTS => [
                'id' => [32, true], 'decision_id' => [32, true], 'recipient' => [190, true], 'target_type' => [64, true], 'target_id' => [190, true],
                'action' => [16, true], 'reason' => [64, true], 'ground' => [16, true], 'reference' => [255, false], 'explanation' => [null, true],
                'prompted' => [null, true], 'automated_detection' => [null, true], 'issued_at' => [null, true],
            ],
        ];

        foreach ($columns as $table => $expected) {
            $found = $schema->getTable($table);
            self::assertCount(\count($expected), $found->getColumns(), $table);

            foreach ($expected as $name => [$length, $required]) {
                self::assertSame($length, $found->getColumn($name)->getLength(), $table.'.'.$name);
                self::assertSame($required, $found->getColumn($name)->getNotnull(), $table.'.'.$name);
            }
        }

        self::assertTrue($schema->getTable(ModerationTables::DECISIONS)->getColumn('id')->getFixed());
        self::assertTrue($schema->getTable(ModerationTables::NOTES)->getColumn('id')->getFixed());
        $statements = $schema->getTable(ModerationTables::STATEMENTS);
        self::assertTrue($statements->getColumn('id')->getFixed());
        self::assertTrue($statements->getColumn('decision_id')->getFixed());
        self::assertTrue($statements->hasIndex('idx_phil_moderation_statement_recipient'));
        self::assertSame(['decision_id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $statements->getUniqueConstraint('uniq_phil_moderation_statement_decision')->getColumnNames()));
    }

    public function testTheRowsReadWhatTheDriverGives(): void
    {
        $rows = new Rows($this->connection());

        self::assertSame(3, $rows->number(['n' => '3'], 'n'));
        self::assertSame(3, $rows->number(['n' => 3], 'n'));
        self::assertSame(0, $rows->number(['n' => [3]], 'n'));
        self::assertSame(0, $rows->number([], 'n'));
        self::assertNull($rows->text(['t' => 3], 't'));
        self::assertSame('', $rows->required([], 't'));
        self::assertTrue($rows->flag(['f' => 1], 'f'));
        self::assertTrue($rows->flag(['f' => '1'], 'f'));
        self::assertFalse($rows->flag(['f' => 0], 'f'));
    }

    #[Override]
    protected function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
