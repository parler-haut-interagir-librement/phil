<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Functional\App;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Signs in the user the `X-User` header names, as a firewall would.
 */
final readonly class HeaderUser
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $event->getRequest()->headers->get('X-User');
        $this->tokens->setToken(null === $user ? null : new UsernamePasswordToken(new InMemoryUser($user, null), 'main'));
    }
}
