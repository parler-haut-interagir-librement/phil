<?php

declare(strict_types=1);

namespace Phil\Listings;

use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\Exception\UnknownKind;

/**
 * The declared kinds of listing of an application, one policy per kind.
 */
final readonly class KindPolicies
{
    /** @var array<string, KindPolicy> */
    private array $policies;

    /**
     * @param list<KindPolicy> $policies
     *
     * @throws InvalidListing
     */
    public function __construct(array $policies)
    {
        $byKind = [];

        foreach ($policies as $policy) {
            if (isset($byKind[$policy->kind->value])) {
                throw InvalidListing::because(\sprintf('The kind of listing "%s" is declared twice.', $policy->kind->value));
            }

            $byKind[$policy->kind->value] = $policy;
        }

        $this->policies = $byKind;
    }

    /**
     * @throws UnknownKind
     */
    public function get(ListingKind $kind): KindPolicy
    {
        return $this->policies[$kind->value] ?? throw UnknownKind::named($kind);
    }

    public function has(ListingKind $kind): bool
    {
        return isset($this->policies[$kind->value]);
    }

    /**
     * @return list<ListingKind>
     */
    public function kinds(): array
    {
        return array_values(array_map(static fn (KindPolicy $policy): ListingKind => $policy->kind, $this->policies));
    }
}
