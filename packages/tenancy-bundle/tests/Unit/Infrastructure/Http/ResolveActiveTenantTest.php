<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Http\ResolveActiveTenant;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\FixedReachableTenants;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\InMemoryActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(ResolveActiveTenant::class)]
final class ResolveActiveTenantTest extends TestCase
{
    public function testTheResolvedTenantIsEntered(): void
    {
        $context = new TenantContext();
        $event = $this->event(Request::create('/invoices'));

        $this->listener($context, ['retail'])($event);

        self::assertSame('retail', $context->current()?->value);
        self::assertFalse($event->hasResponse());
    }

    public function testASelectionRequiredSendsTheAccountToTheSelectorWithItsPage(): void
    {
        $context = new TenantContext();
        $event = $this->event(Request::create('/invoices?page=2'));

        $this->listener($context, ['retail', 'wholesale'])($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('select{"target":"\/invoices?page=2"}', $response->getTargetUrl());
        self::assertNull($context->current());
    }

    public function testAnUnsafeRequestIsSentToTheSelectorWithoutItsPage(): void
    {
        $event = $this->event(Request::create('/invoices', 'POST'));

        $this->listener(new TenantContext(), ['retail', 'wholesale'])($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('select[]', $response->getTargetUrl());
    }

    public function testWithoutASelectorRouteTheRequestCarriesOnInNoTenant(): void
    {
        $context = new TenantContext();
        $event = $this->event(Request::create('/invoices'));

        $this->listener($context, ['retail', 'wholesale'], null)($event);

        self::assertFalse($event->hasResponse());
        self::assertNull($context->current());
    }

    public function testWithNoTenantReachableTheRequestCarriesOnInNoTenant(): void
    {
        $context = new TenantContext();
        $event = $this->event(Request::create('/invoices'));

        $this->listener($context)($event);

        self::assertFalse($event->hasResponse());
        self::assertNull($context->current());
    }

    public function testTheSelectorTheSwitchAndTheIgnoredRoutesAreNotResolved(): void
    {
        foreach (['select', 'phil_tenancy_switch', 'login', 'health'] as $route) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $event = $this->event($request);

            $this->listener(new TenantContext(), ['retail', 'wholesale'])($event);

            self::assertFalse($event->hasResponse(), $route);
        }
    }

    /**
     * A request without a route is resolved even without a selector route: no null route is ignored.
     */
    public function testARequestWithoutARouteIsResolved(): void
    {
        $context = new TenantContext();

        $this->listener($context, ['retail'], null)($this->event(Request::create('/')));

        self::assertSame('retail', $context->current()?->value);
    }

    public function testARequestAlreadyInATenantIsNotResolvedAgain(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('wholesale'));

        $event = $this->event(Request::create('/invoices'));

        $this->listener($context, ['retail', 'wholesale'], tokens: $this->signedIn())($event);

        self::assertFalse($event->hasResponse());
        self::assertSame('wholesale', $context->current()?->value);
    }

    /**
     * A session that signed in on no tenant — on the central domain — and whose cookie the hosts share reaches a host
     * of a tenant the account cannot act in: the request is refused, whatever its route.
     */
    public function testAnAccountIsRefusedOnTheHostOfATenantItCannotReach(): void
    {
        foreach ([null, 'select', 'phil_tenancy_switch', 'login'] as $route) {
            $context = new TenantContext();
            $context->enter(TenantId::fromString('shop'));
            $request = Request::create('/invoices');
            $request->attributes->set('_route', $route);

            try {
                $this->listener($context, ['retail', 'wholesale'], tokens: $this->signedIn())($this->event($request));
                self::fail(\sprintf('The account acted in a tenant it cannot reach, on the route %s.', $route ?? 'none'));
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('This account cannot act in the tenant "shop".', $accessDeniedHttpException->getMessage());
            }
        }
    }

    public function testAVisitorWhoIsNotSignedInStaysInTheHostsTenant(): void
    {
        foreach ([new TokenStorage(), $this->signedIn(new NullToken())] as $tokens) {
            $context = new TenantContext();
            $context->enter(TenantId::fromString('shop'));
            $event = $this->event(Request::create('/'));

            $this->listener($context, ['retail'], tokens: $tokens)($event);

            self::assertFalse($event->hasResponse());
            self::assertSame('shop', $context->current()?->value);
        }
    }

    /**
     * Without the security component, nothing tells a visitor from an account: the reachable tenants decide alone.
     */
    public function testWithoutTheTokenStorageTheReachableTenantsDecide(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('shop'));

        $this->listener($context, ['shop'])($this->event(Request::create('/')));
        self::assertSame('shop', $context->current()?->value);

        $this->expectException(AccessDeniedHttpException::class);
        $this->listener($context, ['retail'])($this->event(Request::create('/')));
    }

    public function testASubRequestIsNotResolvedAgain(): void
    {
        $context = new TenantContext();
        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::SUB_REQUEST);

        $this->listener($context, ['retail'])($event);

        self::assertNull($context->current());
    }

    private function signedIn(?TokenInterface $token = null): TokenStorage
    {
        $tokens = new TokenStorage();
        $tokens->setToken($token ?? new UsernamePasswordToken(new InMemoryUser('ana', null), 'main'));

        return $tokens;
    }

    /**
     * @param list<string> $reachable
     */
    private function listener(TenantContext $context, array $reachable = [], ?string $selector = 'select', ?TokenStorage $tokens = null): ResolveActiveTenant
    {
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => $route.json_encode($parameters, \JSON_THROW_ON_ERROR));

        $activeTenant = new ActiveTenant(
            new InMemoryActiveTenantStore(),
            new FixedReachableTenants(ReachableTenants::of(...array_map(TenantId::fromString(...), $reachable))),
        );

        return new ResolveActiveTenant($activeTenant, $context, $urls, $selector, ['login', 'health'], $tokens);
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
