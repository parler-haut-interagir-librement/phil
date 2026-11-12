<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Application\SecurityHeaders;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Reads the overrides of the main request's route (its `_browser_security` default, as soon as the route is known) and
 * controller (its `#[BrowserSecurity]` attributes, class first), then writes the headers on its response.
 *
 * A header the response already carries stays, unless an override names it. Nothing is kept between requests.
 */
final readonly class SecurityHeadersListener
{
    /**
     * @param string       $group         the name of the endpoint of the reports
     * @param list<string> $excludedPaths prefixes of the paths whose responses get no header
     */
    public function __construct(
        private SecurityHeaders $securityHeaders,
        private ReportEndpoint $endpoint,
        private string $group = HeaderBuilder::DEFAULT_GROUP,
        private array $excludedPaths = [],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest() && $request->attributes->has(ProtectionOverride::ROUTE_DEFAULT)) {
            RequestState::of($request)->addProtectionOverride(ProtectionOverride::fromArray($request->attributes->get(ProtectionOverride::ROUTE_DEFAULT)));
        }
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $state = RequestState::of($event->getRequest());

        foreach ($event->getAttributes(BrowserSecurity::class) as $attribute) {
            $override = $attribute->toOverride();
            $override->assertAfterRouting();
            $state->addProtectionOverride($override);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || array_any($this->excludedPaths, static fn (string $prefix): bool => str_starts_with($request->getPathInfo(), $prefix))) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $resolved = $this->securityHeaders->resolve($request->getPathInfo(), RequestState::of($request)->protectionOverrides, $request->isSecure());

        foreach ($resolved->defaults as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        foreach ($resolved->forced as $name => $value) {
            $headers->set($name, $value);

            if (null === $value) {
                $headers->remove($name);
            }
        }

        $uri = $resolved->reports ? $this->endpoint->uri($request) : null;

        if (null !== $uri && !$headers->has(HeaderBuilder::REPORTING_ENDPOINTS)) {
            $headers->set(HeaderBuilder::REPORTING_ENDPOINTS, $this->group.'="'.$uri.'"');
        }
    }
}
