<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Security;

use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;

/**
 * Grants `API_SCOPE` on a scope name to a request authenticated by an access token holding that scope:
 *
 * ```php
 * $this->denyAccessUnlessGranted(ScopeVoter::ATTRIBUTE, 'projects:write');
 * ```
 *
 * A request authenticated otherwise — a session, another authenticator — is denied, unless the configuration lets
 * those through, for the controllers a member reaches both from the site and through the API.
 *
 * @extends Voter<string, string>
 */
final class ScopeVoter extends Voter
{
    public const string ATTRIBUTE = 'API_SCOPE';

    public function __construct(
        private readonly bool $otherAuthentications = false,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && \is_string($subject);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$token->hasAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE)) {
            return $this->otherAuthentications && $token->getUser() instanceof UserInterface;
        }

        $scopes = $token->hasAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE) ? $token->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE) : null;

        return \is_array($scopes) && \in_array($subject, $scopes, true);
    }
}
