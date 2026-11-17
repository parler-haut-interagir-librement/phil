<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Phil\LtiBundle\Application\ScorePublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends again the scores of the tool whose message the bus never took — or, without a bus, that the platform could not
 * take yet —, as a scheduled task runs it: each score is claimed before it is sent, so that two runs at once never send
 * it twice within the delay, and running it again sends nothing new.
 */
#[AsCommand(name: 'phil:lti:recover-scores', description: 'Sends again the LTI scores whose message was never dispatched')]
final readonly class RecoverScoresCommand
{
    public function __construct(
        private ScorePublisher $publisher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The seconds since a score was saved, or last claimed, before it is sent again')]
        int $delay = 300,
        #[Option('The most scores sent by this run')]
        int $limit = 100,
    ): int {
        if ($delay < 60 || $limit < 1 || $limit > 1000) {
            $io->error('The delay is 60 seconds or more, and the limit 1 to 1,000.');

            return Command::INVALID;
        }

        $io->success(\sprintf('Sent again %d scores.', $this->publisher->recover($delay, $limit)));

        return Command::SUCCESS;
    }
}
