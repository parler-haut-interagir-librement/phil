<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Phil\SettingsBundle\Infrastructure\Security\SecurityCurrentUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SecurityCurrentUser::class)]
final class SecurityCurrentUserTest extends TestCase
{
    public function testTheUserIdentifierOfTheToken(): void
    {
        $tokens = new TokenStorage();
        $user = new SecurityCurrentUser($tokens);

        self::assertNull($user->id(), 'no token');

        $tokens->setToken(new NullToken());
        self::assertNull($user->id(), 'a token without user');

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.com', null), 'main'));
        self::assertSame('ada@example.com', $user->id());
        self::assertNull(new SecurityCurrentUser()->id(), 'without Symfony Security');
    }
}
