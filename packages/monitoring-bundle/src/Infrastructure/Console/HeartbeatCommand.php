<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Console;

use Override;
use Phil\MonitoringBundle\Application\Heartbeats;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:monitoring:heartbeat <task>.
 *
 * Beats a task, for a cron job to call once its work succeeded: `backup.sh && bin/console phil:monitoring:heartbeat
 * backup`. Fails for a task that is not declared.
 */
#[AsCommand(name: 'phil:monitoring:heartbeat', description: 'Records that a scheduled task ran')]
final class HeartbeatCommand extends Command
{
    public function __construct(
        private readonly Heartbeats $heartbeats,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('task', InputArgument::REQUIRED, 'The task, as declared under phil_monitoring.heartbeats.tasks');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $task = $input->getArgument('task');
        $task = \is_string($task) ? $task : '';

        if (!$this->heartbeats->beat($task)) {
            $output->writeln(\sprintf('<error>The task "%s" is not declared under phil_monitoring.heartbeats.tasks.</error>', $task));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
