<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Phil\PrivacyBundle\Application\Contract\DataSubjectUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class TestUser implements UserInterface, DataSubjectUser
{
    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return 'member@example.org';
    }

    #[Override]
    public function dataSubjectIdentifier(): string
    {
        return 'member-1';
    }
}
