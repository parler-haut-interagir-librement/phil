<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Console;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:tenancy:rls:policies [--disable].
 *
 * Prints the SQL that puts every tenant-scoped table under the row-level security of PostgreSQL, one statement per
 * line, for a migration; `--disable` prints the statements that take them out of it, for its `down()`. Nothing is
 * run.
 */
#[AsCommand(name: 'phil:tenancy:rls:policies', description: 'Prints the row-level security policies of the tenant-scoped tables, for a migration')]
final class RowLevelSecurityPoliciesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RowLevelSecurityPolicies $policies,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('disable', null, InputOption::VALUE_NONE, 'The statements that remove the policies');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tables = $this->policies->tables($this->entityManager);

        if ([] === $tables) {
            $output->writeln('-- No entity is tenant-scoped.');
        }

        foreach ($tables as $table) {
            foreach (true === $input->getOption('disable') ? $this->policies->disable($table) : $this->policies->enable($table) as $statement) {
                $output->writeln($statement.';');
            }
        }

        return Command::SUCCESS;
    }
}
