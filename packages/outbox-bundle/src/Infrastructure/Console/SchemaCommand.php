<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the SQL that creates the outbox table when its connection lacks it, and runs it with `--force`: for an
 * application without the ORM, or before its first migration. A table that is there is left as it is.
 */
#[AsCommand(name: 'phil:outbox:schema', description: 'Shows, or creates with --force, the outbox table missing from its connection')]
final readonly class SchemaCommand
{
    public function __construct(
        private Connection $connection,
        private OutboxTable $table,
    ) {
    }

    /**
     * @throws Exception when the database fails
     */
    public function __invoke(SymfonyStyle $io, #[Option('Run the statements instead of showing them')] bool $force = false): int
    {
        if ($this->connection->createSchemaManager()->tablesExist([$this->table->name])) {
            $io->success(\sprintf('The outbox table %s is there.', $this->table->name));

            return Command::SUCCESS;
        }

        $sql = $this->connection->getDatabasePlatform()->getCreateTableSQL($this->table->definition($this->connection->getDatabasePlatform()));

        if (!$force) {
            $io->writeln(array_map(static fn (string $statement): string => $statement.';', $sql));
            $io->note('The outbox table is missing: run again with --force to create it.');

            return Command::SUCCESS;
        }

        foreach ($sql as $statement) {
            $this->connection->executeStatement($statement);
        }

        $io->success(\sprintf('Created: %s.', $this->table->name));

        return Command::SUCCESS;
    }
}
