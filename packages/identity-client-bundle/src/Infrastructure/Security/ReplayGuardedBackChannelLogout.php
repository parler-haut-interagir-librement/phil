<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Contract\ReplayFloor;
use Phil\IdentityClientBundle\Application\Contract\ReplayStore;
use Phil\IdentityClientBundle\Application\Event\ProviderSessionEnded;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;
use Symfony\Component\Security\Http\Oidc\OidcLogoutToken;

/**
 * The back-channel logout of the firewall: the native verification and validation, with each `jti` accepted once.
 *
 * It stands in for the native service behind the native endpoint, which only calls {@see logout()}. The entry lives at
 * least as long as the token can still pass the `exp` check, so a token the store forgot is one the clock refuses.
 *
 * With a replay floor, a token issued before it is refused before its `jti` is looked up: a store that lost its
 * entries cannot tell a new token from one it already accepted.
 */
final readonly class ReplayGuardedBackChannelLogout
{
    public function __construct(
        private OidcSignatureVerifier $signatureVerifier,
        private OidcLogoutToken $logoutToken,
        private OidcDiscovery $discovery,
        private string $clientId,
        private OidcEndedSessions $endedSessions,
        private ReplayStore $replays,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private int $replayTtl,
        private ?ReplayFloor $floor = null,
    ) {
    }

    /**
     * @throws AuthenticationException when the token is not one the provider issued, or was already accepted
     */
    public function logout(#[SensitiveParameter] string $logoutToken): void
    {
        try {
            $claims = $this->signatureVerifier->verify($logoutToken);
        } catch (AuthenticationException $authenticationException) {
            $this->logger->warning('The back-channel logout token could not be verified.');

            throw new AuthenticationException('The logout token could not be verified.', $authenticationException->getCode(), previous: $authenticationException);
        }

        $issuer = $this->discovery->getConfiguration()['issuer'] ?? null;

        try {
            $sid = $this->logoutToken->validateClaims($claims, \is_string($issuer) ? $issuer : '', $this->clientId);
        } catch (AuthenticationException $authenticationException) {
            $this->logger->warning('The back-channel logout token was refused.');

            throw $authenticationException;
        }

        $jti = $claims['jti'] ?? null;
        if (!\is_string($jti) || '' === $jti) {
            $this->logger->warning('The back-channel logout token was refused.');

            throw new AuthenticationException('Invalid logout token: the "jti" claim must be a non-empty string.');
        }

        if ($this->floor instanceof ReplayFloor && $this->floor->floor() > $this->issuedAt($claims)) {
            $this->logger->warning('A back-channel logout token issued before the replay floor was refused.');

            throw new AuthenticationException('The logout token was issued before the replay floor.');
        }

        if (!$this->replays->claim($jti, $this->entryLifetime($claims['exp'] ?? null))) {
            $this->logger->warning('A back-channel logout token was presented again.');

            throw new AuthenticationException('The logout token was already used.');
        }

        $this->endedSessions->record($sid);
        $this->dispatcher->dispatch(new ProviderSessionEnded($sid));
    }

    /**
     * The `iat` the native validation already required to be a number.
     *
     * @param array<array-key, mixed> $claims
     */
    private function issuedAt(array $claims): float|int
    {
        $issuedAt = $claims['iat'] ?? null;
        \assert(\is_int($issuedAt) || \is_float($issuedAt));

        return $issuedAt;
    }

    private function entryLifetime(mixed $expiresAt): int
    {
        return is_numeric($expiresAt)
            ? max($this->replayTtl, (int) $expiresAt - $this->clock->now()->getTimestamp() + 1)
            : $this->replayTtl;
    }
}
