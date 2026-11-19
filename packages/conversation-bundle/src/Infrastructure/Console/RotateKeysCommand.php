<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Console;

use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seals again, under the current master key, every subject and body sealed under a previous one; with `--dry-run`,
 * says only how many remain.
 */
#[AsCommand(name: 'phil:conversation:rotate-keys', description: 'Seals the conversations again under the current master key')]
final readonly class RotateKeysCommand
{
    public function __construct(
        private KeyRotation $rotation,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Say how many texts remain under a previous key, without sealing them again')]
        bool $dryRun = false,
        #[Option(description: 'How many texts of each kind a batch seals again')]
        int $batch = KeyRotation::BATCH,
    ): int {
        if ($batch < 1) {
            $io->error('A batch seals at least one text.');

            return Command::INVALID;
        }

        $pending = $this->rotation->pending();

        if ($dryRun) {
            $io->writeln(\sprintf('%d subject(s) and %d message(s) are sealed under a previous key.', $pending['subjects'], $pending['bodies']));

            return Command::SUCCESS;
        }

        $subjects = 0;
        $bodies = 0;

        do {
            $done = $this->rotation->rotate($batch);
            $subjects += $done['subjects'];
            $bodies += $done['bodies'];
        } while ($done['subjects'] + $done['bodies'] > 0);

        $io->success(\sprintf('%d subject(s) and %d message(s) sealed again under the current key.', $subjects, $bodies));

        return Command::SUCCESS;
    }
}
