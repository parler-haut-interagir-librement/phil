<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application;

use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerQuery;

/**
 * Answers a WebFinger query from every resolver: the descriptors of those that know the resource, merged in their
 * order, with only the links of the relations asked for.
 */
final readonly class WebFinger
{
    /**
     * @param iterable<WebFingerResolver> $resolvers
     */
    public function __construct(
        private iterable $resolvers,
    ) {
    }

    /**
     * @return ?WebFingerDescriptor `null` when no resolver knows the resource
     */
    public function describe(WebFingerQuery $query): ?WebFingerDescriptor
    {
        $descriptor = null;

        foreach ($this->resolvers as $resolver) {
            $found = $resolver->resolve($query->resource);

            if ($found instanceof WebFingerDescriptor) {
                $descriptor = $descriptor?->merge($found) ?? $found;
            }
        }

        return $descriptor?->withRels($query->rels);
    }
}
