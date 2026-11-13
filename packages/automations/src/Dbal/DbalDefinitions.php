<?php

declare(strict_types=1);

namespace Phil\Automations\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;
use Phil\Automations\Exception\InvalidDefinition;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * The definitions in one table of a Doctrine DBAL connection: one row per version, its JSON as `DefinitionWriter`
 * writes it, the published version of an enabled automation marked. A version once kept never changes: publishing it
 * again with other steps is refused, so that a run never finds steps it did not start with.
 */
final readonly class DbalDefinitions implements Definitions
{
    public const string TABLE = 'phil_automation_definition';

    /** @var non-empty-string */
    private string $table;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        string $table = self::TABLE,
        private DefinitionReader $reader = new DefinitionReader(),
        private DefinitionWriter $writer = new DefinitionWriter(),
    ) {
        $this->table = Sql::tableName($table);
    }

    /**
     * Adds the table to a schema that lacks it.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->tableDefinition())->create();
    }

    #[Override]
    public function enabledFor(string $triggerType): iterable
    {
        $rows = $this->connection->fetchFirstColumn(
            \sprintf('SELECT definition FROM %s WHERE trigger_type = ? AND enabled = 1 ORDER BY automation_id', $this->table),
            [$triggerType],
            [ParameterType::STRING],
        );

        foreach ($rows as $json) {
            yield $this->reader->read(Sql::string($json));
        }
    }

    #[Override]
    public function get(AutomationId $id, int $version): ?AutomationDefinition
    {
        $json = $this->connection->fetchOne(
            \sprintf('SELECT definition FROM %s WHERE automation_id = ? AND version = ?', $this->table),
            [$id->value, $version],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return false === $json ? null : $this->reader->read(Sql::string($json));
    }

    #[Override]
    public function publish(AutomationDefinition $definition, bool $enabled = true): void
    {
        $json = $this->writer->write($definition);
        $this->connection->beginTransaction();

        try {
            $kept = $this->connection->fetchOne(
                \sprintf('SELECT definition FROM %s WHERE automation_id = ? AND version = ?', $this->table),
                [$definition->id->value, $definition->version],
                [ParameterType::STRING, ParameterType::INTEGER],
            );

            if (false !== $kept && $kept !== $json) {
                throw InvalidDefinition::because(\sprintf('Version %d of "%s" is kept with other steps: publish it as a new version.', $definition->version, $definition->id));
            }

            $this->connection->executeStatement(\sprintf('UPDATE %s SET enabled = 0 WHERE automation_id = ?', $this->table), [$definition->id->value], [ParameterType::STRING]);

            if (false === $kept) {
                $this->connection->insert($this->table, [
                    'automation_id' => $definition->id->value,
                    'version' => $definition->version,
                    'trigger_type' => $definition->trigger->type,
                    'enabled' => 0,
                    'definition' => $json,
                    'published_at' => $this->clock->now()->getTimestamp(),
                ], [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER]);
            }

            $this->connection->executeStatement(
                \sprintf('UPDATE %s SET enabled = ? WHERE automation_id = ? AND version = ?', $this->table),
                [$enabled ? 1 : 0, $definition->id->value, $definition->version],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER],
            );
            $this->connection->commit();
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    #[Override]
    public function disable(AutomationId $id): void
    {
        $this->connection->executeStatement(\sprintf('UPDATE %s SET enabled = 0 WHERE automation_id = ?', $this->table), [$id->value], [ParameterType::STRING]);
    }

    private function tableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                Column::editor()->setUnquotedName('automation_id')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('version')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('trigger_type')->setTypeName(Types::STRING)->setLength(128)->create(),
                Column::editor()->setUnquotedName('enabled')->setTypeName(Types::SMALLINT)->create(),
                Column::editor()->setUnquotedName('definition')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('published_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('automation_id', 'version')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->table.'_trigger')->setUnquotedColumnNames('trigger_type', 'enabled')->create())
            ->create();
    }
}
