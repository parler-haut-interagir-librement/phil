<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Console;

use Override;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\ImportWizardException;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\RowRejection;
use Phil\SurveyBundle\Application\Import\QuestionCsvProfile;
use Phil\SurveyBundle\Application\Import\QuestionImports;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads the questions of a questionnaire from a CSV file: the dry run by default, line by line, and the import with
 * `--write`.
 */
#[AsCommand(name: 'phil:survey:import-questions', description: 'Previews, then saves, the questions of a questionnaire read from a CSV file')]
final class ImportQuestionsCommand extends Command
{
    public function __construct(
        private readonly QuestionImports $imports,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The CSV file');
        $this->addArgument('key', InputArgument::REQUIRED, 'The key of the questionnaire, created when it does not exist');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'The title of the questionnaire, should the import create it');
        $this->addOption('write', null, InputOption::VALUE_NONE, 'Save the rows the dry run accepts');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $key = $input->getArgument('key');
        $key = \is_string($key) ? $key : '';

        $title = $input->getOption('title');

        try {
            $source = CsvRowSource::fromFile(\is_string($file) ? $file : '', limits: QuestionCsvProfile::limits());
        } catch (ImportWizardException $importWizardException) {
            $output->writeln('<error>'.OutputFormatter::escape($importWizardException->getMessage()).'</error>');

            return Command::INVALID;
        }

        try {
            $plan = $this->imports->plan($source, $key);
        } catch (ImportWizardException|InvalidQuestionnaire $refused) {
            $output->writeln('<error>'.OutputFormatter::escape($refused->getMessage()).'</error>');

            return Command::FAILURE;
        }

        foreach ($plan->preview() as $line) {
            $output->writeln(\sprintf('Line %d: %s', $line->line, $line->action->value));

            foreach ($line->errors as $error) {
                $output->writeln('  '.OutputFormatter::escape($plan->describe($error)));
            }
        }

        $output->writeln(\sprintf('%d to create, %d to replace, %d refused.', $plan->creates(), $plan->updates(), $plan->refusals()));

        if (true !== $input->getOption('write')) {
            $output->writeln('Nothing is saved: run the command again with --write to save the rows accepted.');

            return 0 === $plan->refusals() ? Command::SUCCESS : Command::FAILURE;
        }

        $result = $this->imports->commit($plan, $key, \is_string($title) ? $title : null);
        $this->refusedByTarget($plan, $result->rejections, $output);
        $output->writeln(\sprintf('%d created, %d replaced, %d refused.', $result->created, $result->updated, \count($result->rejections)));

        return [] === $result->rejections ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<RowRejection> $rejections
     */
    private function refusedByTarget(ImportPlan $plan, array $rejections, OutputInterface $output): void
    {
        $previewed = array_map(static fn (RowRejection $rejection): int => $rejection->line, $plan->rejections);

        foreach ($rejections as $rejection) {
            if (\in_array($rejection->line, $previewed, true)) {
                continue;
            }

            $output->writeln(\sprintf('Line %d: refused', $rejection->line));

            foreach ($rejection->errors as $error) {
                $output->writeln('  '.OutputFormatter::escape($plan->describe($error)));
            }
        }
    }
}
