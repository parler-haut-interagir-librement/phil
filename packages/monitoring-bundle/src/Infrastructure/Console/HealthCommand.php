<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Console;

use Override;
use Phil\MonitoringBundle\Application\HealthChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:monitoring:health.
 *
 * Runs every check and prints one line each, then the overall status; fails while a check is down.
 */
#[AsCommand(name: 'phil:monitoring:health', description: 'Runs the health checks of the site')]
final class HealthCommand extends Command
{
    public function __construct(
        private readonly HealthChecker $checker,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->checker->report();

        foreach ($report->results as $result) {
            $output->writeln(\sprintf('%s: %s, %d ms%s', $result->name, $result->status()->value, $result->durationMilliseconds, '' === $result->outcome->summary ? '' : ' — '.$result->outcome->summary));
        }

        $output->writeln(\sprintf('Overall: %s', $report->status()->value));

        return $report->status()->isAvailable() ? Command::SUCCESS : Command::FAILURE;
    }
}
