<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\Http;

use Override;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;

/**
 * The script nonce of the content security policy phil/browser-security-bundle sends with the response: the one its
 * `csp_nonce('script')` gives, created for the page when nothing asked for it before. Asking for it makes the policy
 * name it, and a page with a nonce is not kept in a shared cache: only a site that wants one asks.
 */
final readonly class ContentSecurityPolicyNonce implements ScriptNonceProvider
{
    public function __construct(
        private ContentSecurityPolicyContext $policy,
    ) {
    }

    #[Override]
    public function scriptNonce(): string
    {
        return $this->policy->nonce(InlineKind::Script);
    }
}
