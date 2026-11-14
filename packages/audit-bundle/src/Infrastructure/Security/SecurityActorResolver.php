<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Security;

use Override;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\AuditActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The account of the Security component's token, when its user names itself with `AuditActor`. A user that does not
 * is not named: its user identifier is often an e-mail address, which the trail never holds.
 */
final readonly class SecurityActorResolver implements ActorResolver
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AuditActor ? $user->auditActorId() : null;
    }
}
