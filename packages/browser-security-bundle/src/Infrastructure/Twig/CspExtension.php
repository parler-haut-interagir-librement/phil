<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Twig;

use Override;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The nonces of the response in templates:
 *
 * - `csp_nonce('script')` and `csp_nonce('style')`, the value of a `nonce` attribute;
 * - `csp_meta()`, the `<meta name="csp-nonce">` tag that Turbo reads to give the scripts and styles it adds the
 *   nonce of the page, allowed for both.
 *
 * Under policies declared with `nonces: false`, `csp_nonce()` gives null, which `html_attr({nonce: csp_nonce()})`
 * leaves out, and `csp_meta()` writes nothing.
 */
final class CspExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentSecurityPolicyContext $context,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->context->nonce(...)),
            new TwigFunction('csp_meta', $this->meta(...), ['is_safe' => ['html']]),
        ];
    }

    private function meta(): string
    {
        $nonce = $this->context->sharedNonce();

        return null === $nonce ? '' : '<meta name="csp-nonce" content="'.$nonce.'">';
    }
}
