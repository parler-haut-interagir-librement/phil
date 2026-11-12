<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Reads the overrides of the main request's route (its `_csp` default) and controller (its `#[ContentSecurityPolicy]`
 * attributes, class first), then writes the policy on its response.
 *
 * A response that already carries a policy, enforced or report-only, keeps it untouched: a controller that wrote its
 * own knows better. A response whose policy lists a nonce is made private, so that no shared cache serves that nonce to
 * everyone. The paths of the profiler and its toolbar, which bring their own nonces, are left out. Nothing is kept
 * between requests.
 */
final readonly class ContentSecurityPolicyListener
{
    public const string REPORT_ROUTE = ReportEndpoint::ROUTE;

    /**
     * @param list<string> $excludedPaths prefixes of the paths whose responses get no header
     */
    public function __construct(
        private HeaderBuilder $builder,
        private ReportEndpoint $endpoint,
        private array $excludedPaths = [],
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $state = RequestState::of($request);

        if ($request->attributes->has('_csp')) {
            $state->addOverride(PolicyOverride::fromArray($request->attributes->get('_csp')));
        }

        foreach ($event->getAttributes(ContentSecurityPolicy::class) as $attribute) {
            $state->addOverride($attribute->toOverride());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || array_any($this->excludedPaths, static fn (string $prefix): bool => str_starts_with($request->getPathInfo(), $prefix))) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if (!$headers->has(HeaderBuilder::ENFORCE) && !$headers->has(HeaderBuilder::REPORT_ONLY)) {
            $state = RequestState::of($request);
            $state->headers = $this->builder->build($state, $this->endpoint->uri($request));
            $headers->add($state->headers);
        }

        if ($this->namesNonce($headers) && !$headers->hasCacheControlDirective('private')) {
            $event->getResponse()->setPrivate();
        }
    }

    /**
     * Whether a policy of the response lists a nonce, which a shared cache must not hand to everyone.
     */
    private function namesNonce(ResponseHeaderBag $headers): bool
    {
        return array_any([HeaderBuilder::ENFORCE, HeaderBuilder::REPORT_ONLY], static fn (string $header): bool => str_contains($headers->get($header, ''), "'nonce-"));
    }
}
