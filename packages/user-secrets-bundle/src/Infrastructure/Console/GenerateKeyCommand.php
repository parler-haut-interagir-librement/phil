<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:user-secrets:generate-key.
 *
 * Prints a new random master key of 32 bytes in base64url without padding, the form a `sodium://` DSN of Symfony's
 * KeyManagement component reads in its `keys[<id>]` option: to put in the application's secrets — an environment
 * variable or the Symfony secrets vault — and name in that DSN. Nothing is written anywhere else.
 */
#[AsCommand(name: 'phil:user-secrets:generate-key', description: 'Prints a new master key for a local sodium:// KMS')]
final class GenerateKeyCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        $output->writeln(sodium_bin2base64($key, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
        sodium_memzero($key);

        return Command::SUCCESS;
    }
}
