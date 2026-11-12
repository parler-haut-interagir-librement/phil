<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional\App;

use Override;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class RecordingProvisioner implements UserProvisioner
{
    public ?VerifiedIdentity $last = null;

    public bool $exists = true;

    #[Override]
    public function provision(VerifiedIdentity $identity): UserInterface
    {
        $this->last = $identity;

        return new InMemoryUser($identity->subject, null, ['ROLE_USER']);
    }

    #[Override]
    public function find(string $subject): ?UserInterface
    {
        return $this->exists ? new InMemoryUser($subject, null, ['ROLE_USER']) : null;
    }
}
