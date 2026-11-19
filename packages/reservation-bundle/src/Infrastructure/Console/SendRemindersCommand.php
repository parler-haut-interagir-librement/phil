<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Console;

use Override;
use Phil\ReservationBundle\Application\Reminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:reservation:send-reminders` sends the reminders due, each once; run it every few minutes.
 */
#[AsCommand(name: 'phil:reservation:send-reminders', description: 'Sends the reminders of the reservations that are due')]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly Reminders $reminders,
        private readonly int $batch,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d reminders sent.', $this->reminders->send($this->batch)));

        return Command::SUCCESS;
    }
}
