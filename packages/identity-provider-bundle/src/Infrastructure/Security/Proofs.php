<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The authentication proofs Symfony Security 8.2 records on a token: each method proven (RFC 8176), with the
 * timestamp it was proven at.
 */
final class Proofs
{
    /** The methods that prove a second factor, or one strong enough to stand for two. */
    public const array STRONG = ['hwk', 'otp', 'mfa', 'sms', 'swk'];

    private function __construct()
    {
    }

    /**
     * @return array<string, int>
     */
    public static function of(?TokenInterface $token): array
    {
        if (!$token instanceof TokenInterface || !method_exists($token, 'getAuthenticationProofs')) {
            return [];
        }

        return $token->getAuthenticationProofs();
    }

    /**
     * Whether one of the methods was proven at or after the timestamp.
     *
     * @param list<string> $methods
     */
    public static function provenSince(?TokenInterface $token, array $methods, int $since): bool
    {
        return array_any(self::of($token), static fn (int $at, string $method): bool => $at >= $since && \in_array($method, $methods, true));
    }
}
