<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Console;

use Override;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:scheduled-transitions:run --limit=100.
 *
 * Applies the transitions that are due, for an application that runs it from cron rather than from Symfony
 * Scheduler; fails when one failed.
 */
#[AsCommand(name: 'phil:scheduled-transitions:run', description: 'Applies the scheduled transitions that are due')]
final class RunTransitionsCommand extends Command
{
    public function __construct(
        private readonly Scheduler $scheduler,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many transitions to apply at most', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');

        if (!\is_string($limit) || 1 !== preg_match('/^[1-9]\d{0,5}$/D', $limit)) {
            $output->writeln('<error>The limit is a whole number from 1 to 999999.</error>');

            return Command::INVALID;
        }

        $report = $this->scheduler->runDue((int) $limit);
        $output->writeln(\sprintf('%d transition(s) applied, %d failed.', $report->applied, $report->failed));

        return 0 === $report->failed ? Command::SUCCESS : Command::FAILURE;
    }
}
