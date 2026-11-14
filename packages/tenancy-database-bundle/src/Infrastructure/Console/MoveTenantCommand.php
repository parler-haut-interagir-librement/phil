<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Console;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotMoved;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\TenantMover;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:tenancy:database:move <tenant> <shared|database> [--keep-source].
 *
 * Moves a tenant to the shared database or to a database of its own, which must exist and be migrated: its rows are
 * copied, verified, and its placement changed, or the move is rolled back and the tenant stays where it was.
 */
#[AsCommand(name: 'phil:tenancy:database:move', description: 'Moves a tenant to the shared database or to one of its own, verified, or rolled back')]
final class MoveTenantCommand extends Command
{
    public function __construct(
        private readonly TenantMover $mover,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'The reference of the tenant')
            ->addArgument('target', InputArgument::REQUIRED, '"shared", or the name of the dedicated database')
            ->addOption('keep-source', null, InputOption::VALUE_NONE, 'Leaves the rows of the tenant in the database it leaves');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = TenantId::fromString($this->argument($input, 'tenant'));
        $target = $this->argument($input, 'target');
        $placement = 'shared' === $target ? Placement::shared() : Placement::dedicated($target);

        try {
            $report = $this->mover->move($tenant, $placement, true === $input->getOption('keep-source'));
        } catch (TenantNotMoved $tenantNotMoved) {
            $output->writeln('<error>✗</error> '.$tenantNotMoved->getMessage());

            return Command::FAILURE;
        }

        if (!$report->moved()) {
            $output->writeln(\sprintf('The tenant "%s" lives in %s already.', $tenant->value, $placement->toString()));

            return Command::SUCCESS;
        }

        foreach ($report->rows as $table => $rows) {
            $output->writeln(\sprintf('  %s: %d rows', $table, $rows));
        }

        $output->writeln(\sprintf('<info>✓</info> The tenant "%s" moved from %s to %s%s.', $tenant->value, $report->from->toString(), $report->to->toString(), $report->sourcePurged ? '' : '; its rows are still in '.$report->from->toString()));

        return Command::SUCCESS;
    }

    private function argument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        return \is_string($value) ? $value : '';
    }
}
