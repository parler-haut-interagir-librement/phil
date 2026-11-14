<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\RetentionJob;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:retention --confirm.
 *
 * Without `--confirm`, lists what a run would purge. A job line without a purger stops the command before anything is
 * purged, and a job line without a cutoff is named, its period being enforced by something else or by nothing.
 */
#[AsCommand(name: 'phil:privacy:retention', description: 'Applies the retention schedule')]
final class RunRetentionCommand extends Command
{
    public function __construct(
        private readonly RetentionJob $job,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('confirm', null, InputOption::VALUE_NONE, 'Purge: without it, the orders are listed and nothing is done');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $orders = $this->job->plan();
        } catch (MisconfiguredPrivacy $misconfiguredPrivacy) {
            $output->writeln($misconfiguredPrivacy->getMessage());

            return Command::FAILURE;
        }

        foreach ($this->job->jobsWithoutCutoff() as $rule) {
            $output->writeln(\sprintf('%s: no cutoff, the period of this job is not a duration.', $rule->id));
        }

        if (true !== $input->getOption('confirm')) {
            foreach ($orders as $order) {
                $output->writeln(\sprintf('%s: %s, before %s', $order->ruleId, $order->store, $order->cutoff->format('Y-m-d\TH:i:s\Z')));
            }

            $output->writeln('Nothing was purged: add --confirm to purge.');

            return Command::SUCCESS;
        }

        $purged = $this->job->run();

        if (null === $purged) {
            $output->writeln('Another retention run is in progress.');

            return Command::FAILURE;
        }

        foreach ($purged as $rule => $count) {
            $output->writeln(\sprintf('%s: %d purged', $rule, $count));
        }

        return Command::SUCCESS;
    }
}
