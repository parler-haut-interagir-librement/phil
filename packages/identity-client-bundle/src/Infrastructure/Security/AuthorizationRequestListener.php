<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Contract\ReturnToPolicy;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Event\ElevationRequested;
use Phil\IdentityClientBundle\Application\Event\ReturnToDiscarded;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\OidcAuthorizationRequestEvent;

/**
 * Prepares each authorization request: keeps the validated `return_to` in the session, where the provider never sees
 * it, and asks for the `acr_values` of the elevation under way.
 */
final readonly class AuthorizationRequestListener
{
    public function __construct(
        private ReturnToPolicy $policy,
        private AssuranceLevels $levels,
        private ElevationAttempts $attempts,
        private EventDispatcherInterface $dispatcher,
        private string $parameter,
    ) {
    }

    public function __invoke(OidcAuthorizationRequestEvent $event): void
    {
        $request = $event->getRequest();
        $this->keepReturnTo($request, $event->getFirewallName());
        $this->askForLevel($event, $request);
    }

    private function keepReturnTo(Request $request, string $firewall): void
    {
        $query = $request->query->all();
        if (!\array_key_exists($this->parameter, $query)) {
            return;
        }

        $candidate = $query[$this->parameter];
        $target = \is_string($candidate) ? $this->policy->resolve($candidate) : null;
        $key = '_security.'.$firewall.'.target_path';
        if (null === $target) {
            $request->getSession()->remove($key);
            $this->dispatcher->dispatch(new ReturnToDiscarded());

            return;
        }

        $request->getSession()->set($key, $target);
    }

    private function askForLevel(OidcAuthorizationRequestEvent $event, Request $request): void
    {
        $name = $request->attributes->get(AssuranceVoter::REQUEST_ATTRIBUTE);
        $level = \is_string($name) ? $this->levels->get($name) : null;
        if (!$level instanceof AssuranceLevel) {
            $this->attempts->forget($request->getSession());

            return;
        }

        $classes = $this->levels->acceptableClasses($level);
        if ([] !== $classes) {
            $event->setParam('acr_values', implode(' ', $classes));
        }

        $this->attempts->begin($request->getSession(), $level->name);
        $this->dispatcher->dispatch(new ElevationRequested($level->name));
    }
}
