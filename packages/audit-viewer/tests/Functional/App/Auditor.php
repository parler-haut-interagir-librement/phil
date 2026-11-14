<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use Override;
use Phil\AuditBundle\Application\Contract\AuditActor;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account of the back office, named in the trail by its stable identifier.
 */
final readonly class Auditor implements UserInterface, AuditActor
{
    /**
     * @param non-empty-string $id
     * @param list<string>     $roles
     */
    public function __construct(
        private string $id,
        private array $roles,
    ) {
    }

    #[Override]
    public function auditActorId(): string
    {
        return 'acct-'.$this->id;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->id;
    }
}
