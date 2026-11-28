<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * Sign-in with Google by the authorization code flow, on the OpenID Connect pieces of Symfony Security: its discovery
 * of Google's endpoints, its client for the token endpoint, its verification of the ID token's signature against
 * Google's keys, cached, and its check of the claims.
 *
 * `start()` gives the address of Google's authorization endpoint with a state, a nonce and a PKCE challenge (S256),
 * the attempt kept in the session. `finish()` takes the attempt of the state Google gives back, exchanges the code with
 * the verifier, checks the ID token — signature, issuer, audience, times, `azp`, nonce — and maps its claims. The tokens
 * Google answers with are read and dropped: none is kept, and no refresh token is asked for.
 *
 * Every refusal is `external.failed`; its cause goes to the log, without the token or the person's data.
 */
final readonly class GoogleSignIn
{
    public function __construct(
        private OidcDiscovery $discovery,
        private OidcClientInterface $client,
        private OidcSignatureVerifier $signatures,
        private OidcIdToken $idTokens,
        private GoogleSettings $settings,
        private GoogleClaims $claims,
        private SignInAttempts $attempts,
        private ClockInterface $clock,
        private ?SubjectHolds $holds = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The address that sends the person to Google, which will send them back to the callback.
     *
     * @throws AuthenticationException when Google's discovery document cannot be read or announces no secure endpoint
     */
    public function start(SessionInterface $session, string $callbackUrl): string
    {
        $endpoint = $this->discovery->getSecureEndpoint('authorization_endpoint');
        $state = $this->random();
        $nonce = $this->random();
        $verifier = $this->random();

        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->settings->clientId,
            'redirect_uri' => $callbackUrl,
            'scope' => $this->settings->scope(),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->challenge($verifier),
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => $this->settings->prompt,
            'hd' => $this->settings->hostedDomainHint(),
        ];

        try {
            $url = Url::fromString($endpoint);
        } catch (InvalidUrl $invalidUrl) {
            throw new AuthenticationException('Google announces an authorization endpoint that is not an address.', $invalidUrl->getCode(), previous: $invalidUrl);
        }

        $this->attempts->save($session, $state, new SignInAttempt($nonce, $verifier, $callbackUrl, $this->clock->now()->getTimestamp()));

        return $url->toString().(null === $url->query ? '?' : '&').http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * The identity Google vouches for, read from the parameters of the callback.
     *
     * @param array<array-key, mixed> $parameters the query of the callback
     *
     * @throws Refused when the answer does not hold
     */
    public function finish(SessionInterface $session, array $parameters): ExternalIdentity
    {
        $state = $parameters['state'] ?? null;
        $attempt = \is_string($state) && '' !== $state ? $this->attempts->take($session, $state) : null;

        if (!$attempt instanceof SignInAttempt) {
            throw $this->refuse('no sign-in waits for this state');
        }

        if ($attempt->startedAt + $this->settings->attemptLifetime < $this->clock->now()->getTimestamp()) {
            throw $this->refuse('the sign-in waited too long');
        }

        if (\array_key_exists('error', $parameters)) {
            throw $this->refuse('Google answered with an error');
        }

        $code = $parameters['code'] ?? null;

        if (!\is_string($code) || '' === $code) {
            throw $this->refuse('the answer has no code');
        }

        try {
            $this->checkIssuerParameter($parameters['iss'] ?? null);
            $tokens = $this->client->exchangeCode($code, $attempt->redirectUri, $attempt->codeVerifier);
            $idToken = $tokens['id_token'] ?? null;

            if (!\is_string($idToken) || '' === $idToken) {
                throw new AuthenticationException('The token endpoint answered without an ID token.');
            }

            $claims = $this->signatures->verify($idToken);

            // Google's ID tokens name their issuer with or without the scheme.
            if ('accounts.google.com' === ($claims['iss'] ?? null)) {
                $claims['iss'] = GoogleSettings::ISSUER;
            }

            $this->idTokens->validateClaims($claims, GoogleSettings::ISSUER, $this->settings->clientId, $attempt->nonce);
        } catch (AuthenticationException $authenticationException) {
            throw $this->refuse($authenticationException->getMessage());
        }

        $identity = $this->claims->identity($claims);

        if (true === $this->holds?->isHeld(GoogleSettings::NAME, $identity->subject)) {
            throw $this->refuse('Google disabled this account as hijacked');
        }

        return $identity;
    }

    /**
     * RFC 9207: an `iss` in the answer names the issuer that answered, and must be Google.
     */
    private function checkIssuerParameter(mixed $issuer): void
    {
        if (null !== $issuer && (!\is_string($issuer) || GoogleSettings::ISSUER !== rtrim($issuer, '/'))) {
            throw new AuthenticationException('The answer names another issuer.');
        }
    }

    private function refuse(string $cause): Refused
    {
        $this->logger?->info('A sign-in with Google was refused: {cause}.', ['cause' => $cause]);

        return Refused::because('external.failed');
    }

    /**
     * 256 bits, base64url-encoded to 43 characters: a state, a nonce or a PKCE verifier (RFC 7636, 4.1).
     */
    private function random(): string
    {
        return sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function challenge(string $verifier): string
    {
        return sodium_bin2base64(hash('sha256', $verifier, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
