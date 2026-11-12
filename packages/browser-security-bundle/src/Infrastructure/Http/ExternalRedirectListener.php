<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\ExternalRedirectAction;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Uri\WhatWg\Url;

/**
 * Stops a response that sends the browser to another site, unless the host is allowed: the open redirect that an
 * address taken from the request would otherwise become.
 *
 * The target is resolved as a browser resolves it, so that `//evil.example`, `/\evil.example` and a stray tab are seen
 * for what they are. Only its host is logged: an address can carry a token.
 */
final readonly class ExternalRedirectListener
{
    /**
     * @param list<string> $allowed       hosts, or domains written with a leading dot, redirects may go to
     * @param string       $fallback      the path a refused redirect goes to with the action `fallback`
     * @param list<string> $excludedPaths prefixes of the paths left alone
     */
    public function __construct(
        private ExternalRedirectAction $action = ExternalRedirectAction::Refuse,
        private array $allowed = [],
        private string $fallback = '/',
        private array $excludedPaths = [],
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $location = $response->headers->get('Location');
        $request = $event->getRequest();

        if (!$event->isMainRequest() || null === $location || !$response->isRedirection()) {
            return;
        }

        if (array_any($this->excludedPaths, static fn (string $prefix): bool => str_starts_with($request->getPathInfo(), $prefix))) {
            return;
        }

        /** @var list<string>|null $allowed */
        $allowed = RequestState::of($request)->protection(Protection::ExternalRedirects, $this->allowed);

        if (null === $allowed) {
            return;
        }

        $target = Url::parse($location, new Url($request->getUri()));

        if ($target instanceof Url && $this->isAllowed($target, $request, $allowed)) {
            return;
        }

        $this->logger?->warning('A redirect leaves the site for a host not allowed.', ['host' => $target?->getAsciiHost(), 'action' => $this->action->value]);

        match ($this->action) {
            ExternalRedirectAction::Refuse => $event->setResponse(new Response('', Response::HTTP_FORBIDDEN)),
            ExternalRedirectAction::Fallback => $event->setResponse(new RedirectResponse($this->fallback)),
            ExternalRedirectAction::Log => null,
        };
    }

    /**
     * @param list<string> $allowed
     */
    private function isAllowed(Url $target, Request $request, array $allowed): bool
    {
        if (!\in_array($target->getScheme(), ['http', 'https'], true)) {
            return false;
        }

        $host = $target->getAsciiHost() ?? '';

        return $host === new Url($request->getUri())->getAsciiHost() || array_any($allowed, static fn (string $name): bool => $host === $name || (str_starts_with($name, '.') && str_ends_with($host, $name)));
    }
}
