<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the clients of the provider, never their secrets.
 */
#[AsCommand(name: 'phil:identity-provider:client:list', description: 'Lists the clients of the OpenID Connect provider')]
final readonly class ListClientsCommand
{
    public function __construct(
        private ClientRegistry $clients,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->table(['Client', 'Name', 'Type', 'Redirect addresses', 'Scopes', 'State'], array_map(static fn (Client $client): array => [
            $client->id(),
            $client->name(),
            ($client->isPublic() ? 'public' : 'confidential').($client->isFirstParty() ? ', first party' : ''),
            implode("\n", $client->redirectUris()),
            implode(' ', $client->scopes()),
            $client->isDisabled() ? 'disabled' : 'active',
        ], $this->clients->all()));

        return Command::SUCCESS;
    }
}
