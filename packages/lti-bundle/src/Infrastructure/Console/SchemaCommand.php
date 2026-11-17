<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the SQL that brings the bundle's tables of its connection to this version, and runs it with `--force`: for an
 * application without the ORM, or before its first migration. A table missing is created; a table there gets the
 * columns and indexes it lacks, and nothing else — no column is changed or dropped.
 */
#[AsCommand(name: 'phil:lti:schema', description: 'Shows, or runs with --force, the SQL that creates the LTI tables missing and adds the columns they lack')]
final readonly class SchemaCommand
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
    ) {
    }

    /**
     * @throws Exception when the database fails
     */
    public function __invoke(SymfonyStyle $io, #[Option('Run the statements instead of showing them')] bool $force = false): int
    {
        $platform = $this->connection->getDatabasePlatform();
        $manager = $this->connection->createSchemaManager();
        $missing = [];
        $changed = [];
        $sql = [];

        $names = $this->tables->names();
        $comparator = $manager->createComparator();

        foreach ($this->tables->definitions($platform) as $position => $table) {
            $name = $names[$position];

            if (!$manager->tablesExist([$name])) {
                $missing[] = $table;

                continue;
            }

            $current = $manager->introspectTableByUnquotedName($name);
            $columns = array_map($this->identifier(...), $current->getColumns());
            $indexes = array_map($this->identifier(...), $current->getIndexes());
            $editor = $current->edit();
            $lacking = false;

            foreach ($table->getColumns() as $column) {
                if (!\in_array($this->identifier($column), $columns, true)) {
                    $editor->addColumn($column);
                    $lacking = true;
                }
            }

            foreach ($table->getIndexes() as $index) {
                // The secondary indexes are named after their table; the primary key is a constraint the table has.
                if (str_starts_with($this->identifier($index), $name.'_') && !\in_array($this->identifier($index), $indexes, true)) {
                    $editor->addIndex($index);
                    $lacking = true;
                }
            }

            if ($lacking) {
                $changed[] = $name;
                array_push($sql, ...$platform->getAlterTableSQL($comparator->compareTables($current, $editor->create())));
            }
        }

        if ([] === $missing && [] === $changed) {
            $io->success('The LTI tables are up to date.');

            return Command::SUCCESS;
        }

        $sql = [...$platform->getCreateTablesSQL($missing), ...$sql];
        $created = array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $missing);

        if (!$force) {
            $io->writeln(array_map(static fn (string $statement): string => $statement.';', $sql));
            $io->note(\sprintf('%d tables missing, %d tables to change: run again with --force.', \count($missing), \count($changed)));

            return Command::SUCCESS;
        }

        foreach ($sql as $statement) {
            $this->connection->executeStatement($statement);
        }

        $io->success(\sprintf('Created: %s. Changed: %s.', [] === $created ? 'none' : implode(', ', $created), [] === $changed ? 'none' : implode(', ', $changed)));

        return Command::SUCCESS;
    }

    private function identifier(Column|Index $object): string
    {
        return strtolower($object->getObjectName()->getIdentifier()->getValue());
    }
}
