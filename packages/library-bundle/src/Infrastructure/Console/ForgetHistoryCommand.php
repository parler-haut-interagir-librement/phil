<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Console;

use Override;
use Phil\LibraryBundle\Application\Loans;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `phil:library:forget-history` forgets the member of the loans closed longer ago than `history_days`; run it every
 * day. It changes nothing when the history is kept.
 */
#[AsCommand(name: 'phil:library:forget-history', description: 'Forgets the members of the loans closed long enough ago')]
final class ForgetHistoryCommand extends Command
{
    public function __construct(
        private readonly Loans $loans,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d loans forgot their member.', $this->loans->forgetHistory()));

        return Command::SUCCESS;
    }
}
