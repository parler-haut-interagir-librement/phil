<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application\Contract;

/**
 * The nonce of the page's content security policy, for the script element of its structured data: a bundle that sets
 * the policy implements it. A class that implements it is autoconfigured; the first nonce given is used.
 */
interface ScriptNonceProvider
{
    /**
     * The nonce of the current page, or `null` when this provider has none for it.
     */
    public function scriptNonce(): ?string;
}
