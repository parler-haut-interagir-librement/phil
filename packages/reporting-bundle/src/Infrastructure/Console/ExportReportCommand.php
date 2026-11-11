<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Console;

use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Domain\Exception\ReportingException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A report exported from the command line, to a file or to the standard output.
 */
#[AsCommand(name: 'phil:reporting:export', description: 'Exports a report over a period, to a file or to the standard output')]
final readonly class ExportReportCommand
{
    public function __construct(
        private Reports $reports,
        private Periods $periods,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument("The report's key; without it, the reports are listed")]
        string $report = '',
        #[Option("The format's key")]
        string $format = 'csv',
        #[Option('The period: today, yesterday, last_7_days, last_30_days, this_month, last_month, last_12_months, this_year or last_year')]
        string $period = 'last_month',
        #[Option('The file to write; the standard output when omitted')]
        string $output = '',
    ): int {
        if ('' === $report) {
            $io->listing($this->reports->keys());

            return Command::SUCCESS;
        }

        try {
            $request = new ReportRequest($this->periods->period($this->periods->preset($period)));
            $this->reports->definition($report);
            $this->reports->format($format);
            $handle = '' === $output ? \STDOUT : @fopen($output, 'x');

            if (false === $handle) {
                $io->error(\sprintf('The file "%s" cannot be created: it may exist already.', $output));

                return Command::FAILURE;
            }

            $target = ReportStream::of($handle);
            $rows = $this->reports->write($report, $format, $request, $target);
        } catch (ReportingException $reportingException) {
            $io->error($reportingException->getMessage());

            return Command::FAILURE;
        }

        if ('' !== $output) {
            $target->close();
            $io->success(\sprintf('%d rows written to %s.', $rows, $output));
        }

        return Command::SUCCESS;
    }
}
