<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Http;

use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Answers `503 Service Unavailable` with `Retry-After` to every main request while the site is in maintenance, but
 * those of an allowed route — the health endpoints always —, of an allowed IP address or network, or of a user
 * granted an allowed role. The answer is JSON when the request prefers it, the Twig template otherwise, plain text
 * without Twig. The state is read on every request; nothing is kept between two.
 */
final readonly class MaintenanceListener
{
    /**
     * @param list<string> $allowedIps    addresses or networks in CIDR notation, IPv4 or IPv6
     * @param list<string> $allowedRoles  roles, or any attribute the authorization checker grants
     * @param list<string> $allowedRoutes route names
     */
    public function __construct(
        private MaintenanceStore $store,
        private array $allowedIps = [],
        private array $allowedRoles = [],
        private array $allowedRoutes = [],
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
        private ?Environment $twig = null,
        private string $template = '@PhilMonitoring/maintenance.html.twig',
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $state = $this->store->current();
        $request = $event->getRequest();

        if (!$state instanceof MaintenanceState || $this->isAllowed($request)) {
            return;
        }

        $event->setResponse($this->response($request, $state));
    }

    private function isAllowed(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        if (\is_string($route) && \in_array($route, $this->allowedRoutes, true)) {
            return true;
        }

        $ip = $request->getClientIp();

        if (null !== $ip && [] !== $this->allowedIps && IpUtils::checkIp($ip, $this->allowedIps)) {
            return true;
        }

        return array_any($this->allowedRoles, fn (string $role): bool => true === $this->authorizationChecker?->isGranted($role));
    }

    private function response(Request $request, MaintenanceState $state): Response
    {
        $headers = ['Retry-After' => \sprintf('%d', $state->retryAfterSeconds), 'Cache-Control' => 'no-store'];

        if ('json' === $request->getPreferredFormat()) {
            return new JsonResponse(['status' => 'maintenance', 'retry_after' => $state->retryAfterSeconds], Response::HTTP_SERVICE_UNAVAILABLE, $headers);
        }

        if (!$this->twig instanceof Environment) {
            return new Response('The site is under maintenance. Please come back later.', Response::HTTP_SERVICE_UNAVAILABLE, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($this->twig->render($this->template, ['maintenance' => $state]), Response::HTTP_SERVICE_UNAVAILABLE, $headers);
    }
}
