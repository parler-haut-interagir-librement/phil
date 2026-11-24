<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Console;

use Phil\EmailAddressBundle\Application\Bounces\MailboxPoller;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads the messages of the bounce mailbox not handled yet, through the configured `BounceMailbox`, and has the
 * bounce rules decide each notice they give. Run it from the scheduler or cron; it prints one line per message,
 * never an address.
 */
#[AsCommand(name: 'phil:email-address:poll-mailbox', description: 'Reads the bounce mailbox and applies the bounce rules.')]
final readonly class PollMailboxCommand
{
    public function __construct(
        private MailboxPoller $poller,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The most messages to read.')]
        int $limit = 50,
    ): int {
        if ($limit < 1) {
            $io->error('The limit is at least 1.');

            return Command::INVALID;
        }

        $decisions = $this->poller->poll($limit);

        foreach ($decisions as $id => $messageDecisions) {
            $io->writeln(\sprintf('%s: %s', $id, [] === $messageDecisions ? 'ignored' : implode(', ', array_map(static fn (BounceDecision $decision): string => $decision->value, $messageDecisions))));
        }

        $io->writeln(\sprintf('%d message(s) read.', \count($decisions)), SymfonyStyle::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }
}
