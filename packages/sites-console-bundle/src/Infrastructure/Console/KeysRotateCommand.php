<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Console\KeyRotations;
use Phil\SitesConsoleBundle\Application\Exception\RotationRefused;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rotates the console key: stages the configured next key on every site, switches the console to it, retires the
 * previous key from every site, and prints the report per site. It stops at the first site that fails; running it
 * again resumes. Without a next key in the configuration, it prints a new key pair to put there and stops.
 */
#[AsCommand(name: 'phil:sites-console:keys:rotate', description: 'Rotates the console key on every site without cutting any off')]
final readonly class KeysRotateCommand
{
    public function __construct(
        private KeyRotations $rotations,
        private ConsoleSigning $signing,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        if (!$this->signing->next() instanceof SigningKey && !$this->rotations->unfinished() instanceof KeyRotation) {
            $key = SigningKey::generate();
            $io->writeln('The console has no next key. Set this private key as console.next_private_key (an environment variable or a secret), deploy the console, and run this command again:');
            $io->writeln($key->toBase64());
            $io->newLine();
            $io->writeln('Its public key, which the rotation gives every site:');
            $io->writeln($key->verifyingKey()->toBase64());

            return Command::INVALID;
        }

        try {
            $rotation = $this->rotations->run($actor);
        } catch (RotationRefused $rotationRefused) {
            $io->error($rotationRefused->getMessage());

            return Command::FAILURE;
        }

        $rows = [];

        foreach ($rotation->progress() as $row) {
            $rows[] = [$row['site'], $row['stage']->value, $row['retire']->value, $row['message']];
        }

        $io->writeln(\sprintf('Rotation %s to %s: %s', $rotation->id(), $rotation->nextKey()->toBase64(), $rotation->phase()->value));
        $io->table(['Site', 'Next key staged', 'Previous key retired', 'Message'], $rows);

        if (RotationPhase::Finished !== $rotation->phase()) {
            $io->error('The rotation stopped at a site that failed. Fix it, then run this command again to resume.');

            return Command::FAILURE;
        }

        $io->success('Every site accepts the next key only. Now move console.next_private_key to console.private_key on the console, and set agent.console_public_key to '.$rotation->nextKey()->toBase64().' on each site at its next deploy.');

        return Command::SUCCESS;
    }
}
