<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Entitlement;

/**
 * Grants held in memory, keyed by subject: for tests, fixtures and grants an application computes itself. Immutable:
 * `with()` returns a new instance.
 */
final readonly class InMemoryEntitlements implements Entitlements
{
    /**
     * @param array<string, list<Grant>> $grants
     */
    public function __construct(private array $grants = [])
    {
    }

    public function with(string $subject, Grant ...$grants): self
    {
        $all = $this->grants;
        $all[$subject] = [...$all[$subject] ?? [], ...array_values($grants)];

        return new self($all);
    }

    public function grantsOf(string $subject): array
    {
        return $this->grants[$subject] ?? [];
    }
}
