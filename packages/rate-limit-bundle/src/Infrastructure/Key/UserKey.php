<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Key;

use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The `user` strategy: the signed-in user, kept as a keyed hash — `user:` and the HMAC-SHA-256 of the user identifier
 * under the bundle's secret, so that the storage never holds an email address. A request without a user has no key,
 * and the rule does not count it: pair it with an `ip` rule, or take `user_or_ip`.
 */
final readonly class UserKey implements KeyResolver
{
    public function __construct(
        private ?TokenStorageInterface $tokens,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        $user = $this->tokens?->getToken()?->getUser();

        return $user instanceof UserInterface ? 'user:'.hash_hmac('sha256', $user->getUserIdentifier(), $this->secret) : null;
    }
}
