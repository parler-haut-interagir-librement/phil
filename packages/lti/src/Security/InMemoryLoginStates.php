<?php

declare(strict_types=1);

namespace Phil\Lti\Security;

use Override;
use Phil\Lti\Contract\LoginStates;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Login states held in one process, for tests.
 */
final class InMemoryLoginStates implements LoginStates, ResetInterface
{
    /** @var array<string, LoginState> */
    private array $states = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(LoginState $state): void
    {
        $this->states[$state->state] = $state;
    }

    #[Override]
    public function take(string $state): ?LoginState
    {
        $found = $this->states[$state] ?? null;
        unset($this->states[$state]);

        return null !== $found && $found->expiresAt > $this->clock->now() ? $found : null;
    }

    #[Override]
    public function reset(): void
    {
        $this->states = [];
    }
}
