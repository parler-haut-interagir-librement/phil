<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;

/**
 * Reads an authorization request (OpenID Connect Core, section 3.1.2.1) into an `AuthorizationRequest`, or refuses
 * it with the right error, in this order:
 *
 * 1. an unknown or disabled client, or a redirect address it did not register exactly, is shown, never redirected;
 * 2. a parameter given twice, a `request` or `request_uri` object, a response type other than `code`, a response mode
 *    other than `query`, a missing `openid` scope, a PKCE challenge that is `plain`, out of shape, or missing when
 *    the client requires one, a `prompt` of `none` with another value, or an out-of-shape `max_age` goes back to the
 *    client;
 * 3. scopes the client may not ask for are dropped, unknown ones too: only those the provider and the client both
 *    know remain.
 *
 * Every value is bounded: a parameter longer than MAX_PARAMETER is refused.
 */
final readonly class AuthorizationRequestParser
{
    public const int MAX_PARAMETER = 2048;

    /** The prompts the provider honours; it shows no account chooser, so `select_account` is refused, not ignored. */
    public const array PROMPTS = ['none', 'login', 'consent'];

    /**
     * @param list<string> $supportedScopes the scopes the provider knows
     */
    public function __construct(
        private array $supportedScopes,
    ) {
    }

    /**
     * @param array<array-key, mixed>   $query   the parameters, from the query or the form body
     * @param callable(string): ?Client $clients the registered client of an identifier, null when unknown
     *
     * @throws AuthorizationError
     */
    public function parse(array $query, callable $clients): AuthorizationRequest
    {
        $clientId = $this->single($query, 'client_id');
        $client = null === $clientId ? null : $clients($clientId);

        if (!$client instanceof Client || $client->isDisabled()) {
            throw AuthorizationError::shown('invalid_client', 'The client is unknown.');
        }

        $redirectUri = $this->single($query, 'redirect_uri');

        if (null === $redirectUri || !$client->allowsRedirect($redirectUri)) {
            throw AuthorizationError::shown('invalid_request', 'The redirect address is not one the client registered.');
        }

        $state = $this->optional($query, 'state', $redirectUri, null);
        $this->assertFlow($query, $redirectUri, $state);
        $requested = Scopes::split($this->optional($query, 'scope', $redirectUri, $state));

        if (!\in_array('openid', $requested, true)) {
            throw AuthorizationError::redirected('invalid_scope', 'The scope "openid" is required.', $redirectUri, $state);
        }

        $challenge = $this->optional($query, 'code_challenge', $redirectUri, $state);
        $method = $query['code_challenge_method'] ?? null;

        if (null === $challenge ? $client->requiresPkce() || null !== $method : 'S256' !== $method || 1 !== preg_match(Pkce::CHALLENGE_PATTERN, $challenge)) {
            throw AuthorizationError::redirected('invalid_request', 'A PKCE challenge with the S256 method is required.', $redirectUri, $state);
        }

        $prompt = Scopes::split($this->optional($query, 'prompt', $redirectUri, $state));

        if ([] !== array_diff($prompt, self::PROMPTS) || (\in_array('none', $prompt, true) && \count($prompt) > 1)) {
            throw AuthorizationError::redirected('invalid_request', 'The prompt holds known values, and "none" alone.', $redirectUri, $state);
        }

        $maxAge = $this->optional($query, 'max_age', $redirectUri, $state);

        if (null !== $maxAge && 1 !== preg_match('/^\d{1,9}$/D', $maxAge)) {
            throw AuthorizationError::redirected('invalid_request', 'The maximum age is a number of seconds.', $redirectUri, $state);
        }

        return new AuthorizationRequest(
            $client->id(),
            $redirectUri,
            array_values(array_intersect($requested, $this->supportedScopes, $client->scopes())),
            $state,
            $this->optional($query, 'nonce', $redirectUri, $state),
            $challenge,
            $prompt,
            null === $maxAge ? null : (int) $maxAge,
            $this->optional($query, 'login_hint', $redirectUri, $state),
        );
    }

    /**
     * Refuses what the provider does not support: a parameter given twice, request objects, a response type other
     * than `code`, a response mode other than `query`.
     *
     * @param array<array-key, mixed> $query
     */
    private function assertFlow(array $query, string $redirectUri, ?string $state): void
    {
        foreach ($query as $name => $value) {
            if (!\is_string($value) && null !== $value) {
                throw AuthorizationError::redirected('invalid_request', \sprintf('The parameter "%s" is given more than once.', $name), $redirectUri, $state);
            }
        }

        if (isset($query['request']) || isset($query['request_uri'])) {
            throw AuthorizationError::redirected(isset($query['request']) ? 'request_not_supported' : 'request_uri_not_supported', 'Request objects are not supported.', $redirectUri, $state);
        }

        if ('code' !== ($query['response_type'] ?? null)) {
            throw AuthorizationError::redirected('unsupported_response_type', 'Only the authorization code flow is supported.', $redirectUri, $state);
        }

        if ('query' !== ($query['response_mode'] ?? 'query')) {
            throw AuthorizationError::redirected('invalid_request', 'Only the query response mode is supported.', $redirectUri, $state);
        }
    }

    /**
     * A parameter read before the redirect address is trusted: out of shape, it is shown.
     *
     * @param array<array-key, mixed> $query
     */
    private function single(array $query, string $name): ?string
    {
        $value = $query[$name] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value || \strlen($value) > self::MAX_PARAMETER) {
            throw AuthorizationError::shown('invalid_request', \sprintf('The parameter "%s" is out of shape.', $name));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private function optional(array $query, string $name, string $redirectUri, ?string $state): ?string
    {
        $value = $query[$name] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value) || \strlen($value) > self::MAX_PARAMETER) {
            throw AuthorizationError::redirected('invalid_request', \sprintf('The parameter "%s" is out of shape.', $name), $redirectUri, 'state' === $name ? null : $state);
        }

        return $value;
    }
}
