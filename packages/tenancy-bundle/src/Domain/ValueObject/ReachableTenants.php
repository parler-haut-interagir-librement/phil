<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\ValueObject;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Traversable;

/**
 * The tenants an account **can** act in, as resolved by the identity provider's entitlements.
 *
 * The identity provider knows this set; it does not know which tenant is active. Choosing among these is the
 * application's business, and the choice is checked against this set and nothing else, so switching tenants
 * needs no round trip, no new token and no new login.
 *
 * An empty set is legitimate: it is what entitlements resolve to once they are too stale to be served, and it
 * makes every tenant unreachable.
 *
 * @implements IteratorAggregate<int, TenantId>
 */
final readonly class ReachableTenants implements Countable, IteratorAggregate
{
    /**
     * @param list<TenantId> $tenants
     */
    private function __construct(
        private array $tenants,
    ) {
    }

    /**
     * A duplicate is refused rather than collapsed: it means the entitlements were read wrong, and silently
     * deduplicating would hide that.
     */
    public static function of(TenantId ...$tenants): self
    {
        $seen = [];

        foreach ($tenants as $tenant) {
            if (isset($seen[$tenant->value])) {
                throw InvalidValue::because(\sprintf('The tenant "%s" is listed twice.', $tenant->value));
            }

            $seen[$tenant->value] = $tenant;
        }

        return new self(array_values($tenants));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function contains(TenantId $tenant): bool
    {
        return array_any($this->tenants, static fn (TenantId $reachable): bool => $reachable->equals($tenant));
    }

    public function isEmpty(): bool
    {
        return [] === $this->tenants;
    }

    /**
     * The tenant when exactly one is reachable, `null` otherwise. With one tenant there is nothing to choose.
     */
    public function single(): ?TenantId
    {
        return 1 === \count($this->tenants) ? $this->tenants[0] : null;
    }

    /**
     * @return list<TenantId>
     */
    public function toList(): array
    {
        return $this->tenants;
    }

    public function count(): int
    {
        return \count($this->tenants);
    }

    /**
     * @return Traversable<int, TenantId>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tenants);
    }
}
