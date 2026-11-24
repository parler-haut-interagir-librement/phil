<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSp\Contract\PendingRequests;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSpBundle\Application\Event\SamlLogoutReceived;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Single logout: the request the service provider sends once its own session has ended, remembered under its ID until
 * the response comes back, and the messages the identity provider sends to the logout service — its request, verified,
 * announced by `SamlLogoutReceived` and answered, and its response to the service provider's request.
 */
final readonly class SamlLogout
{
    public function __construct(
        private IdentityProviders $providers,
        private MessageFactory $factory,
        private LogoutProcessor $processor,
        private PendingRequests $requests,
        private ClockInterface $clock,
        private EventDispatcherInterface $events,
        private string $logoutTarget = '/',
    ) {
    }

    /**
     * How the browser carries the logout request to the identity provider; null when it has no logout service, when
     * single logout is turned off for it, or when the service provider has no signing key.
     */
    public function start(string $identityProvider, string $nameId, ?string $nameIdFormat, ?string $sessionIndex): ?Outbound
    {
        $sp = $this->providers->serviceProvider($identityProvider);
        $idp = $this->providers->identityProvider($identityProvider);

        if (!$sp->singleLogoutService instanceof Url || !$sp->signing instanceof KeyPair || null === $idp->metadata->singleLogout($idp->requestBinding)) {
            return null;
        }

        $message = $this->factory->logoutRequest($sp, $idp, $nameId, $nameIdFormat, $sessionIndex);
        $this->requests->save(new PendingRequest($message->id, $identityProvider, $this->clock->now(), $this->logoutTarget));

        return MessageSender::send($message, $sp->signing, $message->id);
    }

    public function receive(string $identityProvider, ReceivedMessage $message): LogoutOutcome
    {
        $sp = $this->providers->serviceProvider($identityProvider);
        $idp = $this->providers->identityProvider($identityProvider);

        if ($message->isRequest()) {
            $request = $this->processor->request($message, $idp, $sp);
            $this->events->dispatch(new SamlLogoutReceived($identityProvider, $request));

            return new LogoutOutcome($request, MessageSender::send($this->factory->logoutResponse($sp, $idp, $request->id), $sp->signing, $message->relayState));
        }

        $pending = null === $message->relayState ? null : $this->requests->take($message->relayState);
        $this->processor->response($message, $idp, $sp, $pending?->identityProvider === $identityProvider ? $pending->id : null);

        return new LogoutOutcome(target: $pending->target ?? $this->logoutTarget);
    }
}
