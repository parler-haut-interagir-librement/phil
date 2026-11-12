<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The policy of the current response, from code: its nonces, the hashes of inline code written at run time, and an
 * override decided by a service rather than by a route or an attribute. Everything goes to the main request, so that a
 * fragment rendered in a sub-request shares the nonces of its page.
 *
 * Without a request — a template rendered by a command — a nonce is still given, and taken by no policy.
 */
final readonly class ContentSecurityPolicyContext
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    /**
     * The nonce of the `<script>` or `<style>` elements of the response, the same for all of them.
     */
    public function nonce(InlineKind|string $kind = InlineKind::Script): string
    {
        $kind = $kind instanceof InlineKind ? $kind : InlineKind::from($kind);

        return $this->state()?->nonce($kind) ?? RequestState::newNonce();
    }

    /**
     * The script nonce, allowed for styles too: the value of the `csp-nonce` meta tag that Turbo reads for the scripts
     * and the styles it adds.
     */
    public function sharedNonce(): string
    {
        return $this->state()?->shareScriptNonce() ?? RequestState::newNonce();
    }

    /**
     * Allows an inline `<script>` or `<style>` by the SHA-256 of its exact content.
     */
    public function allowInline(InlineKind|string $kind, string $content): void
    {
        $this->state()?->addHash($kind instanceof InlineKind ? $kind : InlineKind::from($kind), $content);
    }

    /**
     * Changes the policy of the current response, after the overrides of its route and controller.
     */
    public function override(PolicyOverride $override): void
    {
        $this->state()?->addOverride($override);
    }

    private function state(): ?RequestState
    {
        $request = $this->requests->getMainRequest();

        return $request instanceof Request ? RequestState::of($request) : null;
    }
}
