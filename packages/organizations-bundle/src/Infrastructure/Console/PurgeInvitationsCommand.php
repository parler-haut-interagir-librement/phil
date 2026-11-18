<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Console;

use Override;
use Phil\OrganizationsBundle\Application\Invitations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:organizations:purge-invitations --retention=30.
 *
 * Deletes the invitations that expired or were revoked more than the retention ago, a number of days. Run it each day
 * from the scheduler or cron.
 */
#[AsCommand(name: 'phil:organizations:purge-invitations', description: 'Deletes the invitations expired or revoked past the retention')]
final class PurgeInvitationsCommand extends Command
{
    /**
     * @param non-negative-int $retention the days an expired or revoked invitation is kept by default
     */
    public function __construct(
        private readonly Invitations $invitations,
        private readonly int $retention,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('retention', null, InputOption::VALUE_REQUIRED, 'The days an expired or revoked invitation is kept', (string) $this->retention);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retention = filter_var($input->getOption('retention'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 36_500]]);

        if (!\is_int($retention)) {
            $output->writeln('The retention is a whole number of days, from 0 to 36500.');

            return Command::INVALID;
        }

        $output->writeln(\sprintf('%d invitations deleted.', $this->invitations->purge($retention * 86_400)));

        return Command::SUCCESS;
    }
}
