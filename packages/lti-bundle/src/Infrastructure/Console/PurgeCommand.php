<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the nonces, login states and access tokens that expired, as a scheduled task runs it: the states when the
 * tool side is enabled, the tokens when the platform side is.
 */
#[AsCommand(name: 'phil:lti:purge', description: 'Removes the expired LTI nonces, login states and access tokens')]
final readonly class PurgeCommand
{
    public function __construct(
        private DbalNonces $nonces,
        private ?DbalLoginStates $states = null,
        private ?DbalAccessTokens $tokens = null,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('Removed %d nonces, %d login states and %d access tokens.', $this->nonces->purge(), $this->states?->purge() ?? 0, $this->tokens?->purge() ?? 0));

        return Command::SUCCESS;
    }
}
