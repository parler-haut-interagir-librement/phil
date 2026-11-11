<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Application;

use Phil\ConsoleBundle\Domain\Exception\EnvironmentRefused;
use Phil\ConsoleBundle\Domain\ValueObject\EnvironmentGuards;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Holds a command to its guards in the kernel's environment: refuses it where it is forbidden, asks for the
 * environment's name where it needs a confirmation — the console trims the answer —, and refuses a non-interactive run
 * there unless the forcing option is given.
 */
final readonly class EnvironmentGuard
{
    public const string FORCE_OPTION = 'force-environment';

    public function __construct(
        private string $environment,
    ) {
    }

    /**
     * @throws EnvironmentRefused When the command must not run
     */
    public function check(string $command, EnvironmentGuards $guards, InputInterface $input, OutputInterface $output): void
    {
        if ($guards->forbids($this->environment)) {
            throw EnvironmentRefused::forbidden($command, $this->environment);
        }

        if (!$guards->asksConfirmationIn($this->environment)) {
            return;
        }

        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if ($input->hasOption(self::FORCE_OPTION) && true === $input->getOption(self::FORCE_OPTION)) {
            $errors->writeln(\sprintf('<comment>The confirmation of the "%s" environment is skipped by --%s.</comment>', $this->environment, self::FORCE_OPTION));

            return;
        }

        if (!$input->isInteractive()) {
            throw EnvironmentRefused::notInteractive($command, $this->environment);
        }

        $question = new Question(\sprintf('<question>The command "%s" runs in the "%s" environment. Type "%s" to continue:</question> ', $command, $this->environment, $this->environment));

        try {
            $answer = new QuestionHelper()->ask($input, $errors, $question);
        } catch (MissingInputException) {
            $answer = null;
        }

        if (!\is_string($answer) || $this->environment !== $answer) {
            throw EnvironmentRefused::notConfirmed($command, $this->environment);
        }
    }
}
