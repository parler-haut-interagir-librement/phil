<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the SQL that creates the bundle's tables missing from its connection, and runs it with `--force`: for an
 * application without the ORM, or before its first migration. A table that is there is left as it is.
 */
#[AsCommand(name: 'phil:lti:schema', description: 'Shows, or creates with --force, the LTI tables missing from their connection')]
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
        $missing = array_values(array_filter($this->tables->names(), static fn (string $name): bool => !$manager->tablesExist([$name])));

        if ([] === $missing) {
            $io->success('The LTI tables are there.');

            return Command::SUCCESS;
        }

        $sql = $platform->getCreateTablesSQL(array_filter(
            $this->tables->definitions($platform),
            static fn (Table $table): bool => \in_array($table->getObjectName()->toString(), $missing, true),
        ));

        if (!$force) {
            $io->writeln(array_map(static fn (string $statement): string => $statement.';', $sql));
            $io->note(\sprintf('%d tables missing: run again with --force to create them.', \count($missing)));

            return Command::SUCCESS;
        }

        foreach ($sql as $statement) {
            $this->connection->executeStatement($statement);
        }

        $io->success(\sprintf('Created: %s.', implode(', ', $missing)));

        return Command::SUCCESS;
    }
}
