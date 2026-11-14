<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Security;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Security\RememberSignInTenant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[CoversClass(RememberSignInTenant::class)]
final class RememberSignInTenantTest extends TestCase
{
    public function testALoginInATenantRemembersIt(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('rose'));

        $request = $this->requestWithSession();

        new RememberSignInTenant($context)->onLoginSuccess($this->login($request));

        self::assertSame('rose', $request->getSession()->get(RememberSignInTenant::SESSION_KEY));
    }

    public function testALoginInNoTenantForgetsTheTenantOfAnEarlierLogin(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()->set(RememberSignInTenant::SESSION_KEY, 'rose');

        new RememberSignInTenant(new TenantContext())->onLoginSuccess($this->login($request));

        self::assertFalse($request->getSession()->has(RememberSignInTenant::SESSION_KEY));
    }

    public function testALogoutForgetsTheTenant(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()->set(RememberSignInTenant::SESSION_KEY, 'rose');

        new RememberSignInTenant(new TenantContext())->onLogout(new LogoutEvent($request, new NullToken()));

        self::assertFalse($request->getSession()->has(RememberSignInTenant::SESSION_KEY));
    }

    public function testARequestWithoutASessionIsLeftAlone(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('rose'));

        $request = Request::create('/');
        $listener = new RememberSignInTenant($context);

        $listener->onLoginSuccess($this->login($request));
        $listener->onLogout(new LogoutEvent($request, new NullToken()));

        self::assertFalse($request->hasSession());
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function login(Request $request): LoginSuccessEvent
    {
        $user = new InMemoryUser('ana', null);
        $passport = new SelfValidatingPassport(new UserBadge('ana', static fn (): InMemoryUser => $user));

        return new LoginSuccessEvent(self::createStub(AuthenticatorInterface::class), $passport, new NullToken(), $request, null, 'main');
    }
}
