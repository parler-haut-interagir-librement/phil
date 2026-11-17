<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The client named by an attribute of the security token, `client_id` by default, as an authenticator of access
 * tokens sets it from the token it verified; null without a token, without the attribute, or when it is not a
 * non-empty string. A null attribute name reads no client.
 */
final readonly class TokenAttributeClientResolver implements IdempotencyClientResolver
{
    public const string DEFAULT_ATTRIBUTE = 'client_id';

    public function __construct(
        private ?TokenStorageInterface $tokens = null,
        private ?string $attribute = self::DEFAULT_ATTRIBUTE,
    ) {
    }

    #[Override]
    public function client(Request $request): ?string
    {
        $token = $this->tokens?->getToken();

        if (null === $this->attribute || !$token instanceof TokenInterface || !$token->hasAttribute($this->attribute)) {
            return null;
        }

        $client = $token->getAttribute($this->attribute);

        return \is_string($client) && '' !== $client ? $client : null;
    }
}
