<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Console;

use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the expired codes and tokens, and the sessions closed or idle: the tables keep only what is still of use.
 */
#[AsCommand(name: 'phil:identity-provider:purge', description: 'Removes expired codes and tokens, and closed or idle sessions')]
final readonly class PurgeCommand
{
    public function __construct(
        private ClockInterface $clock,
        private ?GrantRepository $grants = null,
        private ?AccountSessionRepository $sessions = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The days an idle session is kept')]
        int $sessionDays = 30,
    ): int {
        $now = $this->clock->now();
        $grants = $this->grants?->purge($now) ?? 0;
        $sessions = $this->sessions?->purge($now->modify(\sprintf('-%d days', max(1, $sessionDays)))) ?? 0;
        $io->success(\sprintf('Codes and tokens removed: %d. Sessions removed: %d.', $grants, $sessions));

        return Command::SUCCESS;
    }
}
