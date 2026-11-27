<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;

/**
 * A named policy and how the bundle sends it: with `'strict-dynamic'` next to the nonces and hashes of a request, so
 * that the scripts they allow may load others, with the reporting directives or without, and with the nonces of the
 * request or with its hashes alone — a transitional policy for pages whose templates still write inline code.
 */
final readonly class PolicyDefinition
{
    public function __construct(
        public Policy $policy,
        public bool $strictDynamic,
        public bool $report,
        public bool $nonces = true,
    ) {
    }
}
