<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Console;

use Override;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:user-secrets:rotate --batch=500.
 *
 * Seals again under the current key every secret another key sealed; afterwards the old keys can leave the
 * configuration. Running it again re-seals nothing more.
 */
#[AsCommand(name: 'phil:user-secrets:rotate', description: 'Seals every user secret again under the current key')]
final class RotateKeysCommand extends Command
{
    public function __construct(
        private readonly KeyRotation $rotation,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'How many secrets are re-sealed and saved at once', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batch = filter_var($input->getOption('batch'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!\is_int($batch)) {
            $output->writeln('The batch is a whole number of at least 1.');

            return Command::INVALID;
        }

        $output->writeln(\sprintf('%d secrets re-sealed under the current key.', $this->rotation->rotate($batch)));

        return Command::SUCCESS;
    }
}
