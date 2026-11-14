<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use Phil\OnboardingBundle\Infrastructure\Security\SecurityCurrentUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SecurityCurrentUser::class)]
final class SecurityCurrentUserTest extends TestCase
{
    public function testTheUserIsNamedByTheirIdentifier(): void
    {
        $tokens = new TokenStorage();
        $user = new SecurityCurrentUser($tokens);
        self::assertNull($user->id());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.com', null), 'main'));
        self::assertSame('ada@example.com', $user->id());

        $tokens->setToken(new NullToken());
        self::assertNull($user->id());

        self::assertNull(new SecurityCurrentUser()->id());
    }
}
