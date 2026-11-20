<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Infrastructure\Console;

use Override;
use Phil\UsageMeter\UsageMeter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:usage-meter:purge.
 *
 * Run by the scheduler once a day: forgets the counters, and their idempotency keys, that no window counts any more.
 * Nothing breaks without it; the store only grows by one row per subject, meter and period.
 */
#[AsCommand(name: 'phil:usage-meter:purge', description: 'Forgets the counters and keys that no window counts any more')]
final class PurgeUsageCommand extends Command
{
    public function __construct(
        private readonly UsageMeter $meter,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d counter(s) and key(s) forgotten.', $this->meter->purge()));

        return Command::SUCCESS;
    }
}
