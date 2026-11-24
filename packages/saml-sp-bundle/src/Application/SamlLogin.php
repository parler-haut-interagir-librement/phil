<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Contract\PendingRequests;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\PendingRequest;
use Phil\SamlSp\Message\ResponseProcessor;
use Psr\Clock\ClockInterface;

/**
 * A sign-in through an identity provider, from the request to the identity. The request is remembered under its ID,
 * which travels as the relay state and comes back with the response; when the sign-in is bound to the browser, the
 * hash of a token the browser keeps in a cookie is remembered with it, and a response carried by another browser is
 * refused. Where the person goes once signed in is a local path, never an address of another site.
 */
final readonly class SamlLogin
{
    public const int MAX_TARGET = 2048;

    public function __construct(
        private IdentityProviders $providers,
        private MessageFactory $factory,
        private ResponseProcessor $processor,
        private PendingRequests $requests,
        private ClockInterface $clock,
        private string $defaultTarget = '/',
    ) {
    }

    public function start(string $identityProvider, ?string $target, bool $bindToBrowser): LoginStart
    {
        $idp = $this->providers->identityProvider($identityProvider);
        $sp = $this->providers->serviceProvider($identityProvider);
        $message = $this->factory->authnRequest($sp, $idp, $this->providers->settings($identityProvider)->forceAuthn);
        $token = $bindToBrowser ? sodium_bin2hex(random_bytes(32)) : null;
        $this->requests->save(new PendingRequest($message->id, $identityProvider, $this->clock->now(), self::localPath($target), null === $token ? null : hash('sha256', $token)));

        return new LoginStart(MessageSender::send($message, $sp->signing, $message->id), $token);
    }

    public function complete(string $identityProvider, string $samlResponse, ?string $relayState, ?string $browserToken): CompletedLogin
    {
        $pending = null === $relayState ? null : $this->requests->take($relayState);

        if ($pending instanceof PendingRequest && $pending->identityProvider !== $identityProvider) {
            throw InvalidMessage::because('The response reached the service of another identity provider than the request.');
        }

        if (null !== $pending?->browserBinding && (null === $browserToken || !hash_equals($pending->browserBinding, hash('sha256', $browserToken)))) {
            throw InvalidMessage::because('The response reached another browser than the one that sent the request.');
        }

        $identity = $this->processor->process($samlResponse, $this->providers->identityProvider($identityProvider), $this->providers->serviceProvider($identityProvider), $pending?->id);
        $target = $pending instanceof PendingRequest ? $pending->target : self::localPath($relayState);

        return new CompletedLogin($identity, $target ?? $this->defaultTarget);
    }

    /**
     * The path when it is a path of this site — it starts with one `/`, holds no backslash nor control character, and
     * is 2048 bytes at most —, null otherwise.
     */
    public static function localPath(?string $target): ?string
    {
        if (null === $target || \strlen($target) > self::MAX_TARGET || 1 !== preg_match('{^/(?![/\\\\])[^\\\\\x00-\x1F\x7F]*$}D', $target)) {
            return null;
        }

        return $target;
    }
}
