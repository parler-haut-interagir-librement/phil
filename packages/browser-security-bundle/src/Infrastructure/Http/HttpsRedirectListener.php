<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sends every plain request of the site to its HTTPS address, as soon as its route is known and before the firewall
 * reads anything of it. It is the rule Symfony's `schemes` of a route and `requires_channel` of an access rule do not
 * write for a whole site; they keep working beside it.
 */
final readonly class HttpsRedirectListener
{
    /**
     * @param list<string> $excludedPaths prefixes of the paths served over both, such as a health check
     */
    public function __construct(
        private bool $enabled = true,
        private array $excludedPaths = [],
        private int $status = RedirectResponse::HTTP_PERMANENTLY_REDIRECT,
        private int $port = 443,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->isSecure()) {
            return;
        }

        if (null === RequestState::of($request)->protection(Protection::HttpsRedirect, $this->enabled ? true : null)) {
            return;
        }

        if (array_any($this->excludedPaths, static fn (string $prefix): bool => str_starts_with($request->getPathInfo(), $prefix))) {
            return;
        }

        $event->setResponse(new RedirectResponse('https://'.$request->getHost().(443 === $this->port ? '' : ':'.$this->port).$request->getRequestUri(), $this->status));
    }
}
