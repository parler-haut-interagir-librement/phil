<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Console;

use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-keys:purge-expired --retention=30.
 *
 * Removes the tokens that expired or were revoked more than the retention ago, a number of days; their owners no
 * longer see them. Run it each day from the scheduler or cron.
 */
#[AsCommand(name: 'phil:api-keys:purge-expired', description: 'Removes the tokens expired or revoked past the retention')]
final class PurgeExpiredTokensCommand extends Command
{
    /**
     * @param non-negative-int $retention the days an expired or revoked token is kept by default
     */
    public function __construct(
        private readonly ApiTokens $tokens,
        private readonly int $retention,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('retention', null, InputOption::VALUE_REQUIRED, 'The days an expired or revoked token is kept', (string) $this->retention);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retention = filter_var($input->getOption('retention'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (!\is_int($retention)) {
            $output->writeln('The retention is a whole number of days, from 0.');

            return Command::INVALID;
        }

        $output->writeln(\sprintf('%d tokens removed.', $this->tokens->purge($retention)));

        return Command::SUCCESS;
    }
}
