<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Phil\TenancyBundle\Application\Contract\HostAliases;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sends a main request for an alias to its canonical host, before any tenant is entered and before the firewall: the
 * same scheme, port, path and query, permanently — `301` for a `GET` or a `HEAD`, `308` for any other method, which
 * the client repeats with its body. A host that is no alias is left to the other listeners.
 *
 * The canonical host comes from the aliases, never from the request: the address a visitor is sent to is always one
 * of the application's hosts.
 */
final readonly class RedirectHostAlias
{
    public function __construct(
        private HostAliases $aliases,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $canonical = $this->aliases->canonicalHostOf($request->getHost());

        if (null === $canonical) {
            return;
        }

        // HttpFoundation's host and port, the port only when it is not the scheme's own.
        $port = substr($request->getHttpHost(), \strlen($request->getHost()));
        $status = \in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true) ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_PERMANENTLY_REDIRECT;

        $event->setResponse(new RedirectResponse($request->getScheme().'://'.$canonical.$port.$request->getRequestUri(), $status));
    }
}
