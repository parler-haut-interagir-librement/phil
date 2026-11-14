<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Console;

use Override;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Database\TenantDatabaseCreator;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationTarget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:tenancy:database:create <tenant> [--no-migrate].
 *
 * Creates the database of a tenant the placement puts in one of its own, if it does not exist yet, then migrates it.
 * A tenant in the shared database needs nothing.
 */
#[AsCommand(name: 'phil:tenancy:database:create', description: 'Creates and migrates the database of a tenant placed in one of its own')]
final class CreateTenantDatabaseCommand extends Command
{
    public function __construct(
        private readonly TenantPlacement $placement,
        private readonly TenantDatabaseCreator $creator,
        private readonly MigrationRunner $runner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'The reference of the tenant')
            ->addOption('no-migrate', null, InputOption::VALUE_NONE, 'Creates the database without migrating it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = TenantId::fromString($this->argument($input, 'tenant'));
        $placement = $this->placement->placementOf($tenant);

        if ($placement->isShared()) {
            $output->writeln(\sprintf('The tenant "%s" lives in the shared database: there is nothing to create.', $tenant->value));

            return Command::SUCCESS;
        }

        $output->writeln($this->creator->create($placement)
            ? \sprintf('<info>✓</info> The database %s was created.', $placement->toString())
            : \sprintf('The database %s exists already.', $placement->toString()));

        if (true === $input->getOption('no-migrate')) {
            return Command::SUCCESS;
        }

        $result = $this->runner->run([new MigrationTarget($placement, $tenant)], 1)[0];

        if ($result->succeeded()) {
            $output->writeln(\sprintf('<info>✓</info> The database %s is migrated.', $placement->toString()));

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('<error>✗</error> The migration of %s failed; the tenant is suspended until it succeeds.', $placement->toString()));
        $output->writeln($result->output, OutputInterface::OUTPUT_RAW);

        return Command::FAILURE;
    }

    private function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : '';
    }
}
