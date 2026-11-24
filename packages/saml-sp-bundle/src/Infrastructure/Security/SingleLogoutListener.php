<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Security;

use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Infrastructure\Http\OutboundResponder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Carries the logout of the application on to the identity provider: when the token was signed in by an identity
 * provider whose single logout is on, the answer of Symfony's logout becomes the signed logout request, after the
 * default listener sets its redirect and before the cookies are cleared. A failure keeps the local logout.
 */
final readonly class SingleLogoutListener
{
    public function __construct(
        private SamlLogout $logout,
        private IdentityProviders $providers,
        private EventDispatcherInterface $events,
    ) {
    }

    #[AsEventListener(event: LogoutEvent::class, priority: 32)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $identityProvider = $this->attribute($token, SamlAttributes::IDENTITY_PROVIDER);
        $nameId = $this->attribute($token, SamlAttributes::NAME_ID);

        if (null === $identityProvider || null === $nameId || !$this->providers->has($identityProvider)) {
            return;
        }

        try {
            $outbound = $this->logout->start($identityProvider, $nameId, $this->attribute($token, SamlAttributes::NAME_ID_FORMAT), $this->attribute($token, SamlAttributes::SESSION_INDEX));
        } catch (SamlException $samlException) {
            $this->events->dispatch(new SamlResponseRefused($identityProvider, $samlException->getMessage(), $samlException::class));

            return;
        }

        if ($outbound instanceof Outbound) {
            $event->setResponse(OutboundResponder::respond($outbound));
        }
    }

    private function attribute(?TokenInterface $token, string $name): ?string
    {
        $value = $token instanceof TokenInterface && $token->hasAttribute($name) ? $token->getAttribute($name) : null;

        return \is_string($value) ? $value : null;
    }
}
