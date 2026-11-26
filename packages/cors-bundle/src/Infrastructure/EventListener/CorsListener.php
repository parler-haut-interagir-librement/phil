<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Infrastructure\EventListener;

use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Application\PolicyResolver;
use Phil\CorsBundle\Domain\CorsPolicy;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Answers the preflights of the configured paths before the router and the firewall see them, and adds the CORS
 * headers to every other response of those paths, error pages included. Sub-requests are left alone. The listener
 * holds no state: the response step finds the policy again from the request.
 */
final readonly class CorsListener
{
    public function __construct(private PolicyResolver $resolver, private CorsHeaders $headers)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$this->headers->isPreflight($request)) {
            return;
        }

        $policy = $this->resolver->resolve($request);

        if ($policy instanceof CorsPolicy) {
            $event->setResponse($this->headers->preflight($request, $policy));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $this->headers->isPreflight($request)) {
            return;
        }

        $policy = $this->resolver->resolve($request);

        if ($policy instanceof CorsPolicy) {
            $this->headers->apply($request, $event->getResponse(), $policy);
        }
    }
}
