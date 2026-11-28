<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints a new Ed25519 key pair: the private key for the configuration of the console or of an agent, the public
 * key for the other side. Nothing is written to disk.
 */
#[AsCommand(name: 'phil:sites-console:keys', description: 'Generates a key pair for the console or for an agent')]
final readonly class KeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $key = SigningKey::generate();
        $io->writeln('Private key (keep it secret, in an environment variable or a secret):');
        $io->writeln($key->toBase64());
        $io->newLine();
        $io->writeln('Public key (give it to the other side):');
        $io->writeln($key->verifyingKey()->toBase64());

        return Command::SUCCESS;
    }
}
