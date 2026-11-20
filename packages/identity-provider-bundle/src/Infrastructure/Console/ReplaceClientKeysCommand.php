<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Replaces the public key set of a client that signs client assertions, for a rotation: publish the next key beside the
 * current one, let the client switch, then replace the set with the next key alone.
 */
#[AsCommand(name: 'phil:identity-provider:client:keys', description: 'Replaces the public keys a client of the OpenID Connect provider signs its client assertions with')]
final readonly class ReplaceClientKeysCommand
{
    public function __construct(
        private ClientRegistry $clients,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The client identifier')]
        string $id,
        #[Argument(description: 'The JSON file of its new public key set')]
        string $jwks,
    ): int {
        $client = $this->clients->get($id);

        if (!$client instanceof Client) {
            $io->error(\sprintf('No client "%s".', $id));

            return Command::FAILURE;
        }

        try {
            $this->clients->replaceJwks($client, KeySetFile::read($jwks));
        } catch (Refused $refused) {
            $io->error(implode(' ', $refused->reasons));

            return Command::FAILURE;
        }

        $io->success(\sprintf('The keys of client "%s" are replaced.', $id));

        return Command::SUCCESS;
    }
}
