<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Key;

use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\RateLimitBundle\Application\IdentifierKey;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The `user` strategy: the signed-in user, kept as a keyed hash — `user:` and the HMAC-SHA-256 of the user identifier,
 * normalized by `IdentifierKey`, under the bundle's secret, so that the storage never holds an email address and one
 * person counts once whatever the case, accents or invisible characters of their identifier. A request without a user has no key,
 * and the rule does not count it: pair it with an `ip` rule, or take `user_or_ip`.
 */
final readonly class UserKey implements KeyResolver
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_rate_limit.user_key';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public function __construct(
        private ?TokenStorageInterface $tokens,
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? 'user:'.hash_hmac('sha256', IdentifierKey::normalize($user->getUserIdentifier()), $this->key) : null;
    }
}
