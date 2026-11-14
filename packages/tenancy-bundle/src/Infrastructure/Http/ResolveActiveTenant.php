<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Resolution\Outcome;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the active tenant once per main request, after the firewall, and enters it. An account that must
 * choose is sent to the selector route, with the page it asked for as `target` when that page can be asked for
 * again; without a selector route, or with no tenant reachable, the request carries on in no tenant and the
 * Doctrine filter fails closed.
 *
 * The selector, the switch and the ignored routes are not resolved: an account that must choose reaches them. Nor is
 * a request already in a tenant, such as one whose host serves a tenant; but a signed-in account must reach that
 * tenant, whatever the route, or the request is refused with a `403`: a session that signed in on no tenant, on a
 * central domain whose cookie the hosts share, never acts in a tenant the account cannot reach. A visitor who is not
 * signed in stays in the host's tenant. Without the token storage of the security component, nothing tells a visitor
 * from an account, and the reachable tenants decide alone.
 */
final readonly class ResolveActiveTenant
{
    /** @var list<string> */
    private array $unresolvedRoutes;

    /**
     * @param list<string> $ignoredRoutes
     */
    public function __construct(
        private ActiveTenant $activeTenant,
        private TenantContext $context,
        private UrlGeneratorInterface $urls,
        private ?string $selectorRoute = null,
        array $ignoredRoutes = [],
        private ?TokenStorageInterface $tokens = null,
    ) {
        $this->unresolvedRoutes = [...$ignoredRoutes, SwitchActiveTenant::ROUTE, ...(null === $selectorRoute ? [] : [$selectorRoute])];
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        $entered = $this->context->current();

        if ($entered instanceof TenantId) {
            if ($this->isSignedIn() && !$this->activeTenant->canReach($entered)) {
                throw new AccessDeniedHttpException(\sprintf('This account cannot act in the tenant "%s".', $entered->value));
            }

            return;
        }

        if (\in_array($request->attributes->get('_route'), $this->unresolvedRoutes, true)) {
            return;
        }

        $resolution = $this->activeTenant->resolve();

        if ($resolution->isActive()) {
            $this->context->enter($resolution->tenant());
        }

        if (Outcome::SelectionRequired === $resolution->outcome && null !== $this->selectorRoute) {
            $parameters = $request->isMethodSafe() ? ['target' => $request->getRequestUri()] : [];
            $event->setResponse(new RedirectResponse($this->urls->generate($this->selectorRoute, $parameters)));
        }
    }

    /**
     * Whether the request carries a signed-in account; without the token storage, every request is taken for one.
     */
    private function isSignedIn(): bool
    {
        return !$this->tokens instanceof TokenStorageInterface || $this->tokens->getToken()?->getUser() instanceof UserInterface;
    }
}
