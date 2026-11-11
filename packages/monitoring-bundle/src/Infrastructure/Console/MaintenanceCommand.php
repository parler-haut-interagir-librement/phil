<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Console;

use DateTimeInterface;
use Override;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:monitoring:maintenance enable [--retry-after=600]
 *     bin/console phil:monitoring:maintenance disable
 *     bin/console phil:monitoring:maintenance status.
 *
 * Puts the site into maintenance, takes it out, or tells whether it is in.
 */
#[AsCommand(name: 'phil:monitoring:maintenance', description: 'Enables or disables the maintenance mode, or shows it')]
final class MaintenanceCommand extends Command
{
    public function __construct(
        private readonly Maintenance $maintenance,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'enable, disable or status', 'status')
            ->addOption('retry-after', null, InputOption::VALUE_REQUIRED, 'The seconds visitors are told to wait; the configured delay when omitted');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retryAfter = $input->getOption('retry-after');

        if (null !== $retryAfter && (!\is_string($retryAfter) || 1 !== preg_match('/^\d{1,9}$/D', $retryAfter))) {
            $output->writeln('<error>--retry-after is a number of seconds.</error>');

            return Command::INVALID;
        }

        switch ($input->getArgument('action')) {
            case 'enable':
                $state = $this->maintenance->enable(\is_string($retryAfter) ? (int) $retryAfter : null);
                $output->writeln(\sprintf('The site is in maintenance; visitors are told to come back in %d seconds.', $state->retryAfterSeconds));

                return Command::SUCCESS;
            case 'disable':
                $this->maintenance->disable();
                $output->writeln('The site is open.');

                return Command::SUCCESS;
            case 'status':
                $state = $this->maintenance->current();
                $output->writeln($state instanceof MaintenanceState ? \sprintf('The site is in maintenance since %s.', $state->since->format(DateTimeInterface::ATOM)) : 'The site is open.');

                return Command::SUCCESS;
            default:
                $output->writeln('<error>The action is enable, disable or status.</error>');

                return Command::INVALID;
        }
    }
}
