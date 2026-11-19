<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Console;

use Override;
use Phil\LibraryBundle\Application\Reminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:library:send-reminders` sends the reminders and notices of the loans that are due, each once; run it every
 * hour or so.
 */
#[AsCommand(name: 'phil:library:send-reminders', description: 'Sends the reminders and overdue notices of the loans')]
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
