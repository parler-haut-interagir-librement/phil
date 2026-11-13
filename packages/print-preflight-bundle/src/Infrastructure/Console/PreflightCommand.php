<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\Console;

use Override;
use Phil\PrintPreflight\Exception\PrintPreflightException;
use Phil\PrintPreflight\Report;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 *     bin/console phil:print-preflight:check measured.json [--profile=booklet] [--format=json].
 *
 * Checks a measured document against a printer profile and prints the findings, one per line, or the report as JSON.
 * It fails when a finding is an error, so a build stops before the file reaches the printer.
 */
#[AsCommand(name: 'phil:print-preflight:check', description: 'Checks a measured document against a printer profile')]
final class PreflightCommand extends Command
{
    public function __construct(
        private readonly Preflighter $preflighter,
        private readonly MeasuredDocument $documents,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The JSON file of the measured document')
            ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'The printer profile; the default one of the configuration otherwise')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'text or json', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getArgument('file');
        $profile = $input->getOption('profile');

        try {
            $report = $this->preflighter->check($this->documents->fromJson($this->filesystem->readFile($file)), \is_string($profile) ? $profile : null);
        } catch (PrintPreflightException|IOException $exception) {
            $output->writeln($exception->getMessage());

            return Command::INVALID;
        }

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode($report->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        } else {
            $this->text($report, $output);
        }

        return $report->passes() ? Command::SUCCESS : Command::FAILURE;
    }

    private function text(Report $report, OutputInterface $output): void
    {
        foreach ($report->findings as $finding) {
            $output->writeln(\sprintf(
                '%s  %s  %s  %s%s',
                $finding->severity->value,
                null === $finding->page ? 'document' : 'page '.$finding->page,
                $finding->code,
                null === $finding->subject ? '' : $finding->subject.': ',
                $finding->message,
            ), OutputInterface::OUTPUT_RAW);
        }

        $output->writeln(\sprintf('%d error(s), %d warning(s), %d notice(s).', \count($report->errors()), \count($report->warnings()), \count($report->notices())));
    }
}
