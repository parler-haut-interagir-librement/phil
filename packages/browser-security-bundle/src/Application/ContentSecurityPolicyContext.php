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
 * Without a request — a template rendered by a command — a nonce is still given, and taken by no policy. When each
 * policy the response sends is declared with `nonces: false`, no nonce is given: the methods return null, so that the
 * templates of a page served under a transitional policy write no `nonce` attribute.
 */
final readonly class ContentSecurityPolicyContext
{
    /**
     * @param HeaderBuilder|null $builder the policies the response sends; without it, every response takes nonces
     */
    public function __construct(
        private RequestStack $requests,
        private ?HeaderBuilder $builder = null,
    ) {
    }

    /**
     * The nonce of the `<script>` or `<style>` elements of the response, the same for all of them, or null when no
     * policy of the response takes nonces.
     */
    public function nonce(InlineKind|string $kind = InlineKind::Script): ?string
    {
        $kind = $kind instanceof InlineKind ? $kind : InlineKind::from($kind);
        $state = $this->state();

        if (!$state instanceof RequestState) {
            return RequestState::newNonce();
        }

        return $this->takesNonces($state) ? $state->nonce($kind) : null;
    }

    /**
     * Whether the policies of the current response, after the overrides decided so far, take nonces.
     */
    public function noncesEnabled(): bool
    {
        $state = $this->state();

        return !$state instanceof RequestState || $this->takesNonces($state);
    }

    /**
     * The nonce of the `<script>` elements, for a caller that takes a closure without arguments.
     */
    public function scriptNonce(): ?string
    {
        return $this->nonce(InlineKind::Script);
    }

    /**
     * The nonce of the `<style>` elements, for a caller that takes a closure without arguments.
     */
    public function styleNonce(): ?string
    {
        return $this->nonce(InlineKind::Style);
    }

    /**
     * The script nonce, allowed for styles too: the value of the `csp-nonce` meta tag that Turbo reads for the scripts
     * and the styles it adds; null when no policy of the response takes nonces.
     */
    public function sharedNonce(): ?string
    {
        $state = $this->state();

        if (!$state instanceof RequestState) {
            return RequestState::newNonce();
        }

        return $this->takesNonces($state) ? $state->shareScriptNonce() : null;
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

    private function takesNonces(RequestState $state): bool
    {
        return !$this->builder instanceof HeaderBuilder || $this->builder->takesNonces($state);
    }

    private function state(): ?RequestState
    {
        $request = $this->requests->getMainRequest();

        return $request instanceof Request ? RequestState::of($request) : null;
    }
}
