<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rotates the signing keys of the database: run it on a schedule, daily for instance. With
 * `oidc.signing_keys.automatic_rotation` false, a run on the schedule changes nothing, and `--force` rotates by hand.
 */
#[AsCommand(name: 'phil:identity-provider:rotate-keys', description: 'Rotates the signing keys of the OpenID Connect provider')]
final readonly class RotateKeysCommand
{
    public function __construct(
        private SigningKeys $keys,
        private bool $automatic = true,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Activate the pending key now, without waiting for its delay — after a key was exposed')]
        bool $now = false,
        #[Option(description: 'Rotate even though automatic rotation is off')]
        bool $force = false,
    ): int {
        if (!$this->automatic && !$force && !$now) {
            $io->note('Automatic rotation is off (oidc.signing_keys.automatic_rotation): nothing changed. Run with --force to rotate by hand.');

            return Command::SUCCESS;
        }

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
