<?php

declare(strict_types=1);

namespace Phil\Automations\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use UnexpectedValueException;

/**
 * The runs in one table of a Doctrine DBAL connection, instants kept as Unix timestamps, the payload and the steps
 * done as JSON — identifiers only, as `Payload` holds.
 *
 * A run is created by an insert that leaves a row of the same id alone (`ON CONFLICT DO NOTHING` on PostgreSQL,
 * `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite), so that one event starts one run. It is saved
 * by an `UPDATE` that names the revision it was read at and raises it: of two workers, one changes the row and the
 * other changes nothing and stops.
 */
final readonly class DbalRunStore implements RunStore
{
    public const string TABLE = 'phil_automation_run';

    private const array COLUMNS = ['id', 'automation_id', 'version', 'subject', 'state', 'step_cursor', 'resume_at', 'attempt', 'payload', 'completed', 'failure', 'revision', 'started_at', 'updated_at'];

    /** @var non-empty-string */
    private string $table;

    public function __construct(
        private Connection $connection,
        string $table = self::TABLE,
    ) {
        $this->table = Sql::tableName($table);
    }

    /**
     * Adds the table to a schema that lacks it: one a migration builds, or the one the ORM generates.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->tableDefinition())->create();
    }

    #[Override]
    public function create(AutomationRun $run): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            Sql::insertUnlessPresent($this->connection->getDatabasePlatform(), $this->table, self::COLUMNS, ['id']),
            $this->row($run),
            $this->types(),
        );
    }

    #[Override]
    public function get(RunId $id): ?AutomationRun
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s WHERE id = ?', implode(', ', self::COLUMNS), $this->table),
            [$id->value],
            [ParameterType::STRING],
        );

        return false === $row ? null : $this->run($row);
    }

    #[Override]
    public function save(AutomationRun $run): bool
    {
        $row = $this->row($run);
        $types = $this->types();
        $row[11] = $run->revision + 1;
        $assignments = implode(', ', array_map(static fn (string $column): string => $column.' = ?', \array_slice(self::COLUMNS, 1)));

        return 1 === (int) $this->connection->executeStatement(
            \sprintf('UPDATE %s SET %s WHERE id = ? AND revision = ?', $this->table, $assignments),
            [...\array_slice($row, 1), $run->id->value, $run->revision],
            [...\array_slice($types, 1), ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from($this->table)
            ->where('state IN (:states)')
            ->andWhere('resume_at IS NULL OR resume_at <= :now')
            ->orderBy('resume_at')
            ->setMaxResults(max(1, $limit))
            ->setParameter('states', [RunState::Running->value, RunState::Waiting->value], ArrayParameterType::STRING)
            ->setParameter('now', $now->getTimestamp(), ParameterType::INTEGER)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(static fn (mixed $id): RunId => RunId::fromString(Sql::string($id)), $ids);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE state NOT IN (?, ?) AND updated_at < ?', $this->table),
            [RunState::Running->value, RunState::Waiting->value, $before->getTimestamp()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    /**
     * @return list<int|string|null>
     */
    private function row(AutomationRun $run): array
    {
        return [
            $run->id->value,
            $run->automationId->value,
            $run->version,
            $run->subject,
            $run->state->value,
            $run->cursor?->toString(),
            $run->resumeAt?->getTimestamp(),
            $run->attempt,
            json_encode($run->payload->values, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT),
            json_encode(array_map(static fn (CompletedStep $step): array => ['step' => $step->stepId, 'action' => $step->action, 'result' => (object) $step->result->values], $run->completed), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            $run->failure,
            $run->revision,
            $run->startedAt->getTimestamp(),
            $run->updatedAt->getTimestamp(),
        ];
    }

    /**
     * @return list<ParameterType>
     */
    private function types(): array
    {
        $string = ParameterType::STRING;
        $integer = ParameterType::INTEGER;

        return [$string, $string, $integer, $string, $string, $string, $integer, $integer, $string, $string, $string, $integer, $integer, $integer];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function run(array $row): AutomationRun
    {
        $cursor = Sql::nullableString($row['step_cursor']);
        $completed = json_decode(Sql::string($row['completed']), true, 8, \JSON_THROW_ON_ERROR);
        $payload = json_decode(Sql::string($row['payload']), true, 4, \JSON_THROW_ON_ERROR);

        if (!\is_array($completed) || !\is_array($payload)) {
            throw new UnexpectedValueException('The JSON columns of a run hold something else.');
        }

        return new AutomationRun(
            RunId::fromString(Sql::string($row['id'])),
            AutomationId::fromString(Sql::string($row['automation_id'])),
            Sql::integer($row['version']),
            Sql::nullableString($row['subject']),
            Payload::of($payload),
            RunState::from(Sql::string($row['state'])),
            null === $cursor ? null : StepPath::fromString($cursor),
            null === $row['resume_at'] ? null : new DateTimeImmutable('@'.Sql::integer($row['resume_at'])),
            Sql::integer($row['attempt']),
            array_values(array_map($this->completedStep(...), $completed)),
            Sql::nullableString($row['failure']),
            Sql::integer($row['revision']),
            new DateTimeImmutable('@'.Sql::integer($row['started_at'])),
            new DateTimeImmutable('@'.Sql::integer($row['updated_at'])),
        );
    }

    private function completedStep(mixed $step): CompletedStep
    {
        if (!\is_array($step) || !\is_array($step['result'] ?? null)) {
            throw new UnexpectedValueException('A step done of a run holds something else.');
        }

        return new CompletedStep(Sql::string($step['step'] ?? null), Sql::string($step['action'] ?? null), Payload::of($step['result']));
    }

    private function tableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::STRING)->setLength(32)->setFixed(true)->create(),
                Column::editor()->setUnquotedName('automation_id')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('version')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('subject')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('state')->setTypeName(Types::STRING)->setLength(24)->create(),
                Column::editor()->setUnquotedName('step_cursor')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('resume_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('attempt')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('completed')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('failure')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('revision')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('started_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName($this->table.'_due')->setUnquotedColumnNames('state', 'resume_at')->create(),
                Index::editor()->setUnquotedName($this->table.'_updated_at')->setUnquotedColumnNames('updated_at')->create(),
            )
            ->create();
    }
}
