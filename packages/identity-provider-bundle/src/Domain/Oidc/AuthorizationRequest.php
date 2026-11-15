<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * An authorization request that passed every check: the client, its registered redirect address, the scopes it may
 * ask for, the state and nonce, the PKCE challenge — none when the client goes without —, and what it asks of the sign-in — `prompt`, `max_age`, a login
 * hint.
 */
final readonly class AuthorizationRequest
{
    /**
     * @param list<string> $scopes
     * @param list<string> $prompt
     */
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public array $scopes,
        public ?string $state,
        public ?string $nonce,
        public ?string $codeChallenge,
        public array $prompt,
        public ?int $maxAge,
        public ?string $loginHint,
    ) {
    }

    public function prompts(string $value): bool
    {
        return \in_array($value, $this->prompt, true);
    }

    /**
     * The parameters to carry the request through the sign-in and the consent, as they came.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $this->state,
            'nonce' => $this->nonce,
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => null === $this->codeChallenge ? null : 'S256',
            'prompt' => [] === $this->prompt ? null : implode(' ', $this->prompt),
            'max_age' => null === $this->maxAge ? null : (string) $this->maxAge,
            'login_hint' => $this->loginHint,
        ], static fn (?string $value): bool => null !== $value);
    }
}
