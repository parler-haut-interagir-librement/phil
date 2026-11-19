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
 *     bin/console phil:user-secrets:rotate --batch=500
 *     bin/console phil:user-secrets:rotate --dry-run.
 *
 * Seals again under the current key every secret another key sealed, and says how many were sealed again, how many
 * another process changed meanwhile and were left as it made them, and which did not open, by owner and name; it fails
 * when one did not. With `--dry-run`, it writes nothing and counts the secrets left to seal again, by key: the old keys
 * can leave the configuration once none is left.
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
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'How many secrets are read and re-sealed at once', '100');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Counts the secrets left to re-seal, by key, without writing');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (true === $input->getOption('dry-run')) {
            return $this->dryRun($output);
        }

        $batch = filter_var($input->getOption('batch'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!\is_int($batch)) {
            $output->writeln('The batch is a whole number of at least 1.');

            return Command::INVALID;
        }

        $report = $this->rotation->rotate($batch);
        $output->writeln(\sprintf('%d secrets re-sealed under the current key.', $report->resealed));

        if (0 < $report->skipped) {
            $output->writeln(\sprintf('%d secrets changed by another process meanwhile, left as it wrote them.', $report->skipped));
        }

        if ([] === $report->unreadable) {
            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('%d secrets did not open and stay under their key:', \count($report->unreadable)));

        foreach ($report->unreadable as $secret) {
            $output->writeln(\sprintf('  %s / %s', $secret['owner']->value, $secret['name']->value));
        }

        return Command::FAILURE;
    }

    private function dryRun(OutputInterface $output): int
    {
        $remaining = $this->rotation->remaining();

        foreach ($remaining as $keyId => $count) {
            $output->writeln(\sprintf('%s: %d secrets to re-seal', $keyId, $count));
        }

        $output->writeln(\sprintf('%d secrets left to re-seal under the current key.', array_sum($remaining)));

        return Command::SUCCESS;
    }
}
