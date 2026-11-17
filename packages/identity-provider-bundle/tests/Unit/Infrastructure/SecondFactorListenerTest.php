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
use Symfony\Component\Security\Core\User\InMemoryUser;

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
        $event = $this->event($route);

        $this->listener()->onRequest($event);

        self::assertSame(!$open, $event->hasResponse());
    }

    public function testASafeRequestIsSentToTheChallengeAndRemembered(): void
    {
        $event = $this->event('app_page', '/page?x=1');

        $this->listener()->onRequest($event);

        self::assertSame('/two-factor', $event->getResponse()?->headers->get('Location'));
        self::assertSame('/page?x=1', $event->getRequest()->getSession()->get(SecondFactorListener::TARGET));
    }

    public function testAnUnsafeRequestIsSentToTheChallengeWithoutBeingRemembered(): void
    {
        $event = $this->event('app_page', '/page', 'POST');

        $this->listener()->onRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertFalse($event->getRequest()->getSession()->has(SecondFactorListener::TARGET));
    }

    /**
     * @return iterable<string, array{array<string, int>, bool}>
     */
    public static function proofs(): iterable
    {
        yield 'a password only' => [['pwd' => 1_800_000_000], true];
        yield 'a code of the application' => [['pwd' => 1_800_000_000, 'otp' => 1_800_000_000], false];
        yield 'a passkey' => [['hwk' => 1_800_000_000], false];
        yield 'a code at the first second' => [['otp' => 0], false];
        yield 'a code before the first second' => [['otp' => -1], true];
    }

    /**
     * @param array<string, int> $proofs
     */
    #[DataProvider('proofs')]
    public function testOnlyAStrongProofLetsTheRequestThrough(array $proofs, bool $challenged): void
    {
        $event = $this->event('app_page');

        $this->listener($proofs)->onRequest($event);

        self::assertSame($challenged, $event->hasResponse());
    }

    public function testNothingIsAskedOfAnAccountWithoutApplication(): void
    {
        $event = $this->event('app_page');

        $this->listener(totp: false)->onRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testNothingIsAskedWithoutAnAccountOrAToken(): void
    {
        $event = $this->event('app_page');
        $this->listener(user: false)->onRequest($event);
        self::assertFalse($event->hasResponse());

        $event = $this->event('app_page');
        $this->listener(token: false)->onRequest($event);
        self::assertFalse($event->hasResponse());
    }

    public function testNothingIsAskedOfASubRequestNorOfARequestWithoutPreviousSession(): void
    {
        $event = $this->event('app_page', type: HttpKernelInterface::SUB_REQUEST);
        $this->listener()->onRequest($event);
        self::assertFalse($event->hasResponse());

        $event = $this->event('app_page', previousSession: false);
        $this->listener()->onRequest($event);
        self::assertFalse($event->hasResponse());

        $event = $this->event(null);
        $this->listener()->onRequest($event);
        self::assertTrue($event->hasResponse(), 'A request without a route is not open.');
    }

    /**
     * @param array<string, int>|null $proofs
     */
    private function listener(?array $proofs = null, bool $totp = true, bool $user = true, bool $token = true): SecondFactorListener
    {
        $services = new Services();
        $factors = new SecondFactors(new InMemorySecondFactorRepository(), new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $account = $services->account();

        if ($totp) {
            $code = Totp::fromBase32($factors->enroll($account)['secret']);
            $factors->confirm($account, $code->at($services->clock->now()->getTimestamp()), false);
        }

        $tokens = new TokenStorage();

        if ($token) {
            $issued = new UsernamePasswordToken($user ? new AccountUser($account) : new InMemoryUser('x', null), 'main');
            $issued->setAuthenticationProofs($proofs ?? ['pwd' => $services->clock->now()->getTimestamp()]);
            $tokens->setToken($issued);
        }

        $routes = new RouteCollection();
        $routes->add(SecondFactorListener::CHALLENGE_ROUTE, new Route('/two-factor'));

        return new SecondFactorListener($tokens, $factors, new UrlGenerator($routes, new RequestContext()), ['app_logout']);
    }

    private function event(?string $route, string $uri = '/somewhere', string $method = 'GET', bool $previousSession = true, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create($uri, $method);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        if ($previousSession) {
            $request->cookies->set($session->getName(), 'id');
        }

        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }
}
