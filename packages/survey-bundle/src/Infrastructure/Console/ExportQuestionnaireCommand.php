<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Console;

use Override;
use Phil\SurveyBundle\Application\Exception\UnknownQuestionnaire;
use Phil\SurveyBundle\Application\Questionnaires;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes a questionnaire in its JSON exchange format on the standard output.
 */
#[AsCommand(name: 'phil:survey:export', description: 'Writes a questionnaire as JSON')]
final class ExportQuestionnaireCommand extends Command
{
    public function __construct(
        private readonly Questionnaires $questionnaires,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'The key of the questionnaire');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->write($this->questionnaires->export(\is_string($key = $input->getArgument('key')) ? $key : ''), false, OutputInterface::OUTPUT_RAW);
        } catch (UnknownQuestionnaire $unknownQuestionnaire) {
            $output->writeln('<error>'.$unknownQuestionnaire->getMessage().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
