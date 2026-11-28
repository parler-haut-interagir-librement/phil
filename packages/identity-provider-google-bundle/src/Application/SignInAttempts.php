<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The sign-ins waiting for Google's answer, in the session, each under a key carrying its state: several tabs may sign
 * in at once. They are capped — the oldest dropped first —, so that a visitor cannot grow the session, and an attempt
 * is taken once, whatever comes of it, so that a callback replayed finds nothing.
 *
 * The state given back by Google is compared with `hash_equals()` to the keys held, never used as a key itself.
 */
final readonly class SignInAttempts
{
    public const string PREFIX = 'phil_identity_provider_google.attempt.';

    public const int MAX_ATTEMPTS = 5;

    public function save(SessionInterface $session, string $state, SignInAttempt $attempt): void
    {
        $keys = $this->keys($session);

        foreach (\array_slice($keys, 0, max(0, \count($keys) - self::MAX_ATTEMPTS + 1)) as $oldest) {
            $session->remove($oldest);
        }

        $session->set(self::PREFIX.$state, [
            'nonce' => $attempt->nonce,
            'code_verifier' => $attempt->codeVerifier,
            'redirect_uri' => $attempt->redirectUri,
            'started_at' => $attempt->startedAt,
        ]);
    }

    /**
     * The attempt of the state, removed from the session; null when none matches, or what it holds is not an attempt.
     */
    public function take(SessionInterface $session, string $state): ?SignInAttempt
    {
        $expected = self::PREFIX.$state;
        $key = array_find($this->keys($session), static fn (string $key): bool => hash_equals($key, $expected));

        if (null === $key) {
            return null;
        }

        $held = $session->remove($key);

        if (!\is_array($held)
            || !\is_string($held['nonce'] ?? null)
            || !\is_string($held['code_verifier'] ?? null)
            || !\is_string($held['redirect_uri'] ?? null)
            || !\is_int($held['started_at'] ?? null)) {
            return null;
        }

        return new SignInAttempt($held['nonce'], $held['code_verifier'], $held['redirect_uri'], $held['started_at']);
    }

    /**
     * @return list<string> the keys of the attempts, the oldest first
     */
    private function keys(SessionInterface $session): array
    {
        return array_values(array_filter(array_map(strval(...), array_keys($session->all())), static fn (string $key): bool => str_starts_with($key, self::PREFIX)));
    }
}
