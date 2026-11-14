<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Application\Contract\AuditActor;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user that names itself in the trail by a stable identifier, and logs in with an e-mail address.
 */
final readonly class ActorUser implements UserInterface, AuditActor
{
    public function __construct(
        private string $id,
    ) {
    }

    #[Override]
    public function auditActorId(): string
    {
        return $this->id;
    }

    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return 'ada@example.org';
    }
}
