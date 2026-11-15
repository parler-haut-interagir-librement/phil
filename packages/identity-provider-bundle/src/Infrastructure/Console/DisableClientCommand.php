<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Disables a client, or gives a confidential one a new secret.
 */
#[AsCommand(name: 'phil:identity-provider:client:disable', description: 'Disables a client of the OpenID Connect provider, or rotates its secret')]
final readonly class DisableClientCommand
{
    public function __construct(
        private ClientRegistry $clients,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The client identifier')]
        string $id,
        #[Option(description: 'Give the client a new secret instead, shown once')]
        bool $rotateSecret = false,
    ): int {
        $client = $this->clients->get($id);

        if (!$client instanceof Client) {
            $io->error(\sprintf('No client "%s".', $id));

            return Command::FAILURE;
        }

        if ($rotateSecret) {
            if ($client->isPublic()) {
                $io->error('A public client has no secret.');

                return Command::FAILURE;
            }

            $io->writeln('Its new secret, shown once: '.$this->clients->rotateSecret($client));

            return Command::SUCCESS;
        }

        $this->clients->disable($client);
        $io->success(\sprintf('Client "%s" disabled.', $id));

        return Command::SUCCESS;
    }
}
