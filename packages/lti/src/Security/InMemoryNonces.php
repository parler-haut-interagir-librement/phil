<?php

declare(strict_types=1);

namespace Phil\Lti\Security;

use DateTimeImmutable;
use Override;
use Phil\Lti\Contract\Nonces;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Nonces remembered in one process, for tests.
 */
final class InMemoryNonces implements Nonces, ResetInterface
{
    /** @var array<string, DateTimeImmutable> */
    private array $seen = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function use(string $scope, string $value, DateTimeImmutable $expiresAt): bool
    {
        $key = $scope."\n".$value;
        $known = $this->seen[$key] ?? null;

        if (null !== $known && $known > $this->clock->now()) {
            return false;
        }

        $this->seen[$key] = $expiresAt;

        return true;
    }

    #[Override]
    public function reset(): void
    {
        $this->seen = [];
    }
}
