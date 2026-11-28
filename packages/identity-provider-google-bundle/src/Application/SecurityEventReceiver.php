<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds;
use Phil\IdentityProviderGoogleBundle\Application\Event\SecurityEventReceived;
use Phil\IdentityProviderGoogleBundle\Application\Exception\TransmitterUnreachable;
use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEvent;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventType;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Acts on the security events a transmitter posts — Google's Cross-Account Protection, or another provider of the
 * Shared Signals Framework:
 *
 * - sessions or tokens revoked, an account disabled or deleted at the provider: the sessions of the account the
 *   subject is linked to are closed;
 * - an account disabled as hijacked (`reason` `hijacking`): the subject is also held, and may not sign in with the
 *   provider until an `account-enabled` event releases it;
 * - a verification event, or an event about a subject linked to no account: nothing more than the acknowledgement.
 *
 * Each event is then dispatched as `SecurityEventReceived`. A token received before is acknowledged and left alone.
 */
final readonly class SecurityEventReceiver
{
    public const string HIJACKING = 'hijacking';

    public function __construct(
        private SecurityEventTokenReader $reader,
        private ReplayGuard $replays,
        private LinkedSessions $sessions,
        private TransmitterSettings $transmitter,
        private ?SubjectHolds $holds = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * @throws InvalidSecurityEventToken when the token is refused
     * @throws TransmitterUnreachable    when the keys of the transmitter cannot be read
     */
    public function receive(string $token): Receipt
    {
        $read = $this->reader->read($token);

        if ($this->replays->isKnown($read->issuer, $read->id)) {
            return new Receipt(true);
        }

        $closed = 0;

        foreach ($read->events as $event) {
            $closed += $this->act($event);
        }

        $this->replays->remember($read->issuer, $read->id);

        return new Receipt(false, \count($read->events), $closed);
    }

    private function act(SecurityEvent $event): int
    {
        $provider = $this->transmitter->provider;
        $subject = $this->transmitter->acceptsSubjectIssuer($event->subjectIssuer) ? $event->subject : null;
        $accountId = null === $subject ? null : $this->sessions->accountOf($provider, $subject);
        $closed = null !== $accountId && $event->type->closesSessions() ? $this->sessions->close($accountId) : 0;

        if (null !== $subject && SecurityEventType::AccountDisabled === $event->type && self::HIJACKING === $event->reason) {
            $this->holds?->hold($provider, $subject);
        }

        if (null !== $subject && SecurityEventType::AccountEnabled === $event->type) {
            $this->holds?->release($provider, $subject);
        }

        $this->dispatcher?->dispatch(new SecurityEventReceived($provider, $event->type, $accountId, $event->reason, $closed));

        return $closed;
    }
}
