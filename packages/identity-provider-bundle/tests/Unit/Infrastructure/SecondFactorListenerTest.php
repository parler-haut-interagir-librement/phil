<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\SecondFactorListener;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[CoversClass(SecondFactorListener::class)]
final class SecondFactorListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function routes(): iterable
    {
        yield 'the challenge' => ['phil_identity_provider_challenge', true];
        yield 'the code posted' => ['phil_identity_provider_code_check', true];
        yield 'the passkey options' => ['phil_identity_provider_passkey_login_options', true];
        yield 'the passkey sign-in' => ['phil_identity_provider_passkey_login', true];
        yield 'a route the configuration lists' => ['app_logout', true];
        yield 'a route named after the challenge' => ['phil_identity_provider_challenge_admin', false];
        yield 'a route named after the code' => ['phil_identity_provider_code', false];
        yield 'a route named after the passkey sign-in' => ['phil_identity_provider_passkey_login_admin', false];
        yield 'a route of the profiler' => ['_profiler', false];
        yield 'an application route starting with an underscore' => ['_admin_export', false];
        yield 'the account page' => ['phil_identity_provider_account', false];
    }

    #[DataProvider('routes')]
    public function testOnlyTheRoutesNamedExactlyStayOpenBeforeTheSecondStep(string $route, bool $open): void
    {
        $services = new Services();
        $factors = new SecondFactors(new InMemorySecondFactorRepository(), new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $account = $services->account();
        $totp = Totp::fromBase32($factors->enroll($account)['secret']);
        $factors->confirm($account, $totp->at($services->clock->now()->getTimestamp()), false);

        $tokens = new TokenStorage();
        $token = new UsernamePasswordToken(new AccountUser($account), 'main');
        $token->setAuthenticationProofs(['pwd' => $services->clock->now()->getTimestamp()]);
        $tokens->setToken($token);
        $routes = new RouteCollection();
        $routes->add(SecondFactorListener::CHALLENGE_ROUTE, new Route('/two-factor'));
        $listener = new SecondFactorListener($tokens, $factors, new UrlGenerator($routes, new RequestContext()), ['app_logout']);

        $request = Request::create('/somewhere');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'id');
        $request->attributes->set('_route', $route);
        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onRequest($event);

        self::assertSame(!$open, $event->hasResponse());
    }
}
