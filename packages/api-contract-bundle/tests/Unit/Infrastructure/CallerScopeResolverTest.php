<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(CallerScopeResolver::class)]
final class CallerScopeResolverTest extends TestCase
{
    public function testTheUserOrElseTheAddress(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $tokens = new TokenStorage();

        self::assertSame('ip:10.0.0.1', new CallerScopeResolver()->scope($request)->value);
        self::assertSame('ip:10.0.0.1', new CallerScopeResolver($tokens)->scope($request)->value);
        $tokens->setToken(new NullToken());
        self::assertSame('ip:10.0.0.1', new CallerScopeResolver($tokens)->scope($request)->value);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('user:ada', new CallerScopeResolver($tokens)->scope($request)->value);

        $noAddress = Request::create('/');
        $noAddress->server->remove('REMOTE_ADDR');
        self::assertSame('ip:unknown', new CallerScopeResolver()->scope($noAddress)->value);
    }
}
