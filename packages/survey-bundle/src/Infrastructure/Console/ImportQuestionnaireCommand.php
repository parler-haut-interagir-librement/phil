<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Console;

use Override;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads a questionnaire from a JSON file of its exchange format and saves it.
 */
#[AsCommand(name: 'phil:survey:import', description: 'Saves a questionnaire read from a JSON file')]
final class ImportQuestionnaireCommand extends Command
{
    public function __construct(
        private readonly Questionnaires $questionnaires,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The JSON file');
        $this->addOption('replace', null, InputOption::VALUE_NONE, 'Replace the questionnaire of the same key');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $file = \is_string($file) ? $file : '';

        $json = is_file($file) && is_readable($file) && filesize($file) <= QuestionnaireJson::MAX_BYTES ? file_get_contents($file) : false;

        if (false === $json) {
            $output->writeln(\sprintf('<error>The file is a readable file of at most %d bytes.</error>', QuestionnaireJson::MAX_BYTES));

            return Command::INVALID;
        }

        try {
            $questionnaire = $this->questionnaires->import($json, true === $input->getOption('replace'));
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            $output->writeln('<error>'.$invalidQuestionnaire->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('The questionnaire "%s" is saved.', $questionnaire->key));

        return Command::SUCCESS;
    }
}
