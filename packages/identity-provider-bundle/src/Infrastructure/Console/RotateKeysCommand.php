<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rotates the signing keys: run it on a schedule, daily for instance.
 */
#[AsCommand(name: 'phil:identity-provider:rotate-keys', description: 'Rotates the signing keys of the OpenID Connect provider')]
final readonly class RotateKeysCommand
{
    public function __construct(
        private SigningKeys $keys,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Activate the pending key now, without waiting for its delay — after a key was exposed')]
        bool $now = false,
    ): int {
        $result = $this->keys->rotate($now);
        $io->success(\sprintf(
            'Keys removed: %d. Activated: %s. Created: %s.',
            $result['removed'],
            $result['activated'] ?? 'none',
            $result['created'] ?? 'none',
        ));

        return Command::SUCCESS;
    }
}
