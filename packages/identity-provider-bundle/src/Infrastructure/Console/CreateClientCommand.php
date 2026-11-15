<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registers a client of the provider and shows its secret, once.
 */
#[AsCommand(name: 'phil:identity-provider:client:create', description: 'Registers a client of the OpenID Connect provider and shows its secret once')]
final readonly class CreateClientCommand
{
    public function __construct(
        private ClientRegistry $clients,
    ) {
    }

    /**
     * @param list<string> $redirectUri
     * @param list<string> $postLogoutRedirectUri
     * @param list<string> $scope
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The client identifier, 3 to 64 letters, digits, "_", "." or "-"')]
        string $id,
        #[Argument(description: 'The name the consent page shows')]
        string $name,
        #[Option(description: 'An exact redirect address; repeat for several')]
        array $redirectUri = [],
        #[Option(description: 'An exact address to come back to after signing out; repeat for several')]
        array $postLogoutRedirectUri = [],
        #[Option(description: 'A scope the client may ask for besides openid; repeat for several')]
        array $scope = [],
        #[Option(description: 'A public client — a single-page or native application —, without a secret')]
        bool $public = false,
        #[Option(description: 'A first-party application, which needs no consent')]
        bool $firstParty = false,
        #[Option(description: 'A confidential client whose authorization requests may go without a PKCE challenge')]
        bool $pkceOptional = false,
    ): int {
        try {
            [$client, $secret] = $this->clients->register($id, $name, $redirectUri, $postLogoutRedirectUri, $scope, $public, $firstParty, !$pkceOptional);
        } catch (Refused $refused) {
            $io->error(implode(' ', $refused->reasons));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Client "%s" registered.', $client->id()));

        if (null !== $secret) {
            $io->writeln('Its secret, shown once: '.$secret);
        }

        return Command::SUCCESS;
    }
}
