<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\FileSigningKeys;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seals a private key — a PEM or a private JSON Web Key — under the key of `secrets.kms_key`, for the file source of
 * signing keys with `sealed: true`. The key is read and checked before it is sealed; the sealed text goes to the
 * standard output, to be written to the file the configuration names.
 */
#[AsCommand(name: 'phil:identity-provider:seal-key', description: 'Seals a private signing key file under the key management service')]
final readonly class SealKeyCommand
{
    public function __construct(
        private SecretCipher $cipher,
        private SigningAlgorithm $algorithm,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The file of the private key, PEM or JSON Web Key')]
        string $path,
    ): int {
        $material = is_file($path) && is_readable($path) && filesize($path) <= FileSigningKeys::MAX_FILE_SIZE ? file_get_contents($path) : false;

        if (!\is_string($material) || '' === trim($material)) {
            $io->error(\sprintf('The file "%s" cannot be read, or is empty or larger than 64 KiB.', $path));

            return Command::FAILURE;
        }

        try {
            $key = PrivateKey::fromMaterial($this->algorithm, 'seal', trim($material));
        } catch (InvalidValue $invalidValue) {
            $io->error($invalidValue->getMessage());

            return Command::FAILURE;
        }

        $io->writeln($this->cipher->seal($key->material(), FileSigningKeys::SEAL_CONTEXT));

        return Command::SUCCESS;
    }
}
