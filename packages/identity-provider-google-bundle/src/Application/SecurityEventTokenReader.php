<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Phil\IdentityProviderGoogleBundle\Application\Exception\TransmitterUnreachable;
use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventToken;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Throwable;

/**
 * Reads a security event token a transmitter posted: its signature checked by Symfony's `OidcSignatureVerifier` against
 * the keys the transmitter's configuration document names, cached; then its claims read, its issuer and audience
 * compared to the transmitter's, and its age bounded.
 */
final readonly class SecurityEventTokenReader
{
    /** The longest token read; Google's are about 1 KiB. */
    public const int MAX_LENGTH = 16_384;

    public function __construct(
        private OidcSignatureVerifier $signatures,
        private TransmitterSettings $transmitter,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidSecurityEventToken when the token is refused
     * @throws TransmitterUnreachable    when the keys of the transmitter cannot be read
     */
    public function read(string $token): SecurityEventToken
    {
        if ('' === $token || \strlen($token) > self::MAX_LENGTH || 2 !== substr_count($token, '.')) {
            throw InvalidSecurityEventToken::malformed('The body is not a security event token in the compact form.');
        }

        try {
            $claims = $this->signatures->verify($token);
        } catch (AuthenticationException $authenticationException) {
            if ($this->isTransport($authenticationException)) {
                throw TransmitterUnreachable::because($authenticationException);
            }

            throw InvalidSecurityEventToken::signature();
        }

        $read = SecurityEventToken::fromClaims($claims);

        if (!$this->transmitter->isIssuer($read->issuer)) {
            throw InvalidSecurityEventToken::issuer();
        }

        if (!$this->transmitter->acceptsAudience($read->audiences)) {
            throw InvalidSecurityEventToken::audience();
        }

        $now = $this->clock->now()->getTimestamp();

        if ($read->issuedAt > $now + $this->transmitter->clockSkew || $read->issuedAt < $now - $this->transmitter->maxAge) {
            throw InvalidSecurityEventToken::malformed('The token was issued out of the time accepted.');
        }

        return $read;
    }

    private function isTransport(Throwable $exception): bool
    {
        for ($cause = $exception; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof HttpException) {
                return true;
            }
        }

        return false;
    }
}
