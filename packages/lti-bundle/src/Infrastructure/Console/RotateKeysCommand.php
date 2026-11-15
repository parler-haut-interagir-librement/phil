<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rotates the signing key — at once, or with `--if-due` only once it is older than the configured lifetime, as a
 * scheduled task runs it — and removes the keys retired before the overlap.
 */
#[AsCommand(name: 'phil:lti:rotate-keys', description: 'Makes a new LTI signing key, keeps the previous one published for the overlap, and removes older ones')]
final readonly class RotateKeysCommand
{
    public function __construct(
        private DbalKeyStore $keys,
        private int $lifetime,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Rotate only when the signing key is older than the lifetime')] bool $ifDue = false): int
    {
        if ($ifDue && !$this->keys->isDue($this->lifetime)) {
            $io->success(\sprintf('The signing key is not due; %d retired keys removed.', $this->keys->prune()));

            return Command::SUCCESS;
        }

        $key = $this->keys->rotate();
        $io->success(\sprintf('The signing key is now "%s"; %d retired keys removed.', $key->keyId(), $this->keys->prune()));

        return Command::SUCCESS;
    }
}
