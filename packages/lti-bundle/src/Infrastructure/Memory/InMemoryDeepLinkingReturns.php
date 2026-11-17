<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Deep linking responses held in one process, for tests.
 */
final class InMemoryDeepLinkingReturns implements DeepLinkingReturns, ResetInterface
{
    /** @var array<string, StoredDeepLinkingReturn> */
    private array $returns = [];

    #[Override]
    public function save(StoredDeepLinkingReturn $return): void
    {
        $this->returns[$return->key] ??= $return;
    }

    #[Override]
    public function find(string $key, DateTimeImmutable $now): ?StoredDeepLinkingReturn
    {
        $return = $this->returns[$key] ?? null;

        return null !== $return && $now < $return->expiresAt ? $return : null;
    }

    #[Override]
    public function markHandled(string $key, DateTimeImmutable $at): void
    {
        $return = $this->returns[$key] ?? null;

        if ($return instanceof StoredDeepLinkingReturn) {
            $this->returns[$key] = new StoredDeepLinkingReturn($return->key, $return->clientId, $return->claims, $return->receivedAt, $return->expiresAt, $at);
        }
    }

    #[Override]
    public function unhandled(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array
    {
        $found = array_filter($this->returns, static fn (StoredDeepLinkingReturn $return): bool => !$return->handledAt instanceof DateTimeImmutable && $return->receivedAt <= $before && $now < $return->expiresAt);
        usort($found, static fn (StoredDeepLinkingReturn $a, StoredDeepLinkingReturn $b): int => [$a->receivedAt, $a->key] <=> [$b->receivedAt, $b->key]);

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        $before = \count($this->returns);
        $this->returns = array_filter($this->returns, static fn (StoredDeepLinkingReturn $return): bool => $now < $return->expiresAt);

        return $before - \count($this->returns);
    }

    #[Override]
    public function reset(): void
    {
        $this->returns = [];
    }
}
