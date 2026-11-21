<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application;

/**
 * A sign-in sent to Google and waiting for its answer: the nonce its ID token must carry, the PKCE verifier its code is
 * exchanged with, the redirect URI the exchange repeats, and when it started.
 */
final readonly class SignInAttempt
{
    public function __construct(
        public string $nonce,
        public string $codeVerifier,
        public string $redirectUri,
        public int $startedAt,
    ) {
    }
}
