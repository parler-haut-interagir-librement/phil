<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use Phil\CoursesBundle\Application\Training\TrainingRun;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:courses:training`: runs the mandatory training now — new members of groups and units, reminders and
 * renewals —, as the schedule does every day; for an application that runs it from cron.
 */
#[AsCommand(name: 'phil:courses:training', description: 'Sends the reminders of the mandatory training and renews the recertifications due.')]
final readonly class TrainingCommand
{
    public function __construct(
        private TrainingRun $run,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $report = $this->run->run();
        $io->success(\sprintf('%d members reached, %d reminders sent, %d completions renewed.', $report->reached, $report->reminded, $report->renewed));

        if (0 < $report->skipped) {
            $io->warning(\sprintf('%d assignments skipped: their members could not be named, or were too many.', $report->skipped));
        }

        return Command::SUCCESS;
    }
}
