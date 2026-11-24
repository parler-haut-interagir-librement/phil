<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\Backup\Backups;
use Phil\Backup\Storage\BackupSet;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:backup:monitor [--max-age=26].
 *
 * Says whether the most recent backup of the directory without a label is fresh: taken less than `max_age_hours`
 * hours ago, 26 by default — a nightly backup and two hours for it to run late. It exits with 0 when it is, 1 when it
 * is older or there is none, for a monitoring system to read. It only lists the directory: it takes no lock, and
 * opens no set.
 */
#[AsCommand(name: 'phil:backup:monitor', description: 'Says whether the most recent backup is recent enough')]
final class MonitorBackupCommand extends Command
{
    public const int DEFAULT_MAX_AGE_HOURS = 26;

    public function __construct(
        private readonly Backups $backups,
        private readonly int $maxAgeHours = self::DEFAULT_MAX_AGE_HOURS,
        private readonly ?ClockInterface $clock = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Hours the most recent backup may be old at most, instead of phil_backup.monitor.max_age_hours');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $option = $input->getOption('max-age');
        $hours = null === $option ? $this->maxAgeHours : (\is_string($option) && 1 === preg_match('/^[1-9]\d{0,5}$/D', $option) ? (int) $option : null);

        if (null === $hours) {
            $output->writeln('The maximum age is a whole number of hours, 1 or more.');

            return Command::INVALID;
        }

        $latest = $this->backups->find(null);

        if (!$latest instanceof BackupSet) {
            $output->writeln('There is no backup in the backup directory.');

            return Command::FAILURE;
        }

        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $age = $now->getTimestamp() - $latest->time->getTimestamp();
        $described = \sprintf('The most recent backup, %s, was taken %s ago', $latest->stem, $this->duration(max(0, $age)));

        if ($age >= $hours * 3600) {
            $output->writeln(\sprintf('%s: more than %d hour(s).', $described, $hours));

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('%s: less than %d hour(s).', $described, $hours));

        return Command::SUCCESS;
    }

    /**
     * Seconds as hours and minutes, such as `27 h 05 min`.
     */
    private function duration(int $seconds): string
    {
        return \sprintf('%d h %02d min', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
