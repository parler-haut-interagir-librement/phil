<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Console;

use Override;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 *     bin/console phil:error-tracking:status.
 *
 * Says how the error tracking runs: the capture points on, whether the store answers and how many groups it keeps,
 * and where the alerts go. A store that does not answer is a failure; alerts without a destination are a warning, the
 * degraded state, and the command still succeeds, so that a deployment can run it without stopping on it.
 */
#[AsCommand(name: 'phil:error-tracking:status', description: 'Tells the capture points, the store and the destination of the alerts of the error tracking')]
final class StatusCommand extends Command
{
    /**
     * @param array<string, bool> $capturePoints whether each capture point is on, by its name
     */
    public function __construct(
        private readonly ErrorGroupReader $reader,
        private readonly AlertDestination $alerts,
        private readonly array $capturePoints,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->capturePoints as $point => $on) {
            $output->writeln(\sprintf('Capture of %s: %s.', $point, $on ? 'on' : 'off'));
        }

        try {
            $groups = $this->reader->countGroups(null);
        } catch (Throwable $throwable) {
            $output->writeln(\sprintf('<error>Store: does not answer (%s).</error>', $throwable::class));
            $this->writeAlerts($output);

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Store: answers, %d group(s) kept.', $groups));
        $this->writeAlerts($output);

        return Command::SUCCESS;
    }

    private function writeAlerts(OutputInterface $output): void
    {
        $output->writeln($this->alerts->isDegraded()
            ? \sprintf('<comment>Alerts: degraded. %s</comment>', $this->alerts->describe())
            : \sprintf('Alerts: %s', $this->alerts->describe()));
    }
}
