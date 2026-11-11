<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Console;

use DateTimeInterface;
use Override;
use Phil\MonitoringBundle\Application\DeadManSwitch;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:monitoring:watch.
 *
 * Runs a round of the dead-man switch — the tasks late since their last alert are alerted — and prints every task;
 * fails while one is late, for a cron job whose failures are mailed.
 */
#[AsCommand(name: 'phil:monitoring:watch', description: 'Alerts the scheduled tasks that have not run in their window')]
final class WatchCommand extends Command
{
    public function __construct(
        private readonly DeadManSwitch $switch,
        private readonly Heartbeats $heartbeats,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $alerted = $this->switch->watch();
        $late = false;

        foreach ($this->heartbeats->statuses() as $status) {
            $late = $late || HeartbeatState::Late === $status->state;
            $output->writeln(\sprintf(
                '%s: %s%s%s',
                $status->task,
                $status->state->value,
                null === $status->lastBeatAt ? '' : ', last run '.$status->lastBeatAt->format(DateTimeInterface::ATOM),
                \in_array($status->task, $alerted, true) ? ', alerted' : '',
            ));
        }

        return $late ? Command::FAILURE : Command::SUCCESS;
    }
}
