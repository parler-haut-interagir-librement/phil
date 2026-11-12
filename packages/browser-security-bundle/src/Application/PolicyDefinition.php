<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;

/**
 * A named policy and how the bundle sends it: with `'strict-dynamic'` next to the nonces and hashes of a request, so
 * that the scripts they allow may load others, and with the reporting directives or without.
 */
final readonly class PolicyDefinition
{
    public function __construct(
        public Policy $policy,
        public bool $strictDynamic,
        public bool $report,
    ) {
    }
}
