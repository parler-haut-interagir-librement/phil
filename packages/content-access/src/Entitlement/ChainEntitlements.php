<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Entitlement;

/**
 * The grants of several sources together, in the order of the sources: a subscription module, an external platform and
 * the application's own enrolments read as one.
 */
final readonly class ChainEntitlements implements Entitlements
{
    /** @var list<Entitlements> */
    private array $sources;

    public function __construct(Entitlements ...$sources)
    {
        $this->sources = array_values($sources);
    }

    public function grantsOf(string $subject): array
    {
        $grants = [];

        foreach ($this->sources as $source) {
            foreach ($source->grantsOf($subject) as $grant) {
                $grants[] = $grant;
            }
        }

        return $grants;
    }
}
