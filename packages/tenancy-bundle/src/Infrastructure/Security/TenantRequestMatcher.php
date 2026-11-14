<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Security;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Matches the requests that act in one of its tenants: the `request_matcher` of a firewall of SecurityBundle, so that
 * each tenant, or each group of tenants, signs its accounts in with its own firewall — its own authenticators, its own
 * provider, its own session context.
 *
 * The tenant is the one of the request when the firewall map is read, before the firewall runs: the tenant the
 * request's host serves. A request in no tenant, or whose tenant is resolved later from the account's choice, is
 * matched by none.
 */
final readonly class TenantRequestMatcher implements RequestMatcherInterface
{
    /** @var array<string, int> the tenant references, as keys */
    private array $tenants;

    /**
     * @param list<string> $tenants the references of the tenants to match
     *
     * @throws InvalidValue when a reference is malformed, or none is given
     */
    public function __construct(
        private TenantContext $context,
        array $tenants,
    ) {
        if ([] === $tenants) {
            throw InvalidValue::because('A firewall of tenants needs at least one tenant.');
        }

        $this->tenants = array_flip(array_map(static fn (string $tenant): string => TenantId::fromString($tenant)->value, $tenants));
    }

    #[Override]
    public function matches(Request $request): bool
    {
        $tenant = $this->context->current();

        return $tenant instanceof TenantId && isset($this->tenants[$tenant->value]);
    }
}
