<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\DoctrineTenantActivation;
use Phil\TenancyBundle\Infrastructure\Http\CurrentTenantValueResolver;
use Phil\TenancyBundle\Infrastructure\Http\ResolveActiveTenant;
use Phil\TenancyBundle\Infrastructure\Http\SwitchActiveTenant;
use Phil\TenancyBundle\Tests\Functional\App\HeaderActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests through the bundle's listener: the tenant resolved from the account's entitlements and its
 * remembered choice, entered in the context, set on the filter, and the ORM reading only that tenant's rows — or
 * none. Then the switch the selector posts to, and the `#[CurrentTenant]` argument.
 */
#[CoversClass(ActiveTenant::class)]
#[CoversClass(ActiveTenantFilter::class)]
#[CoversClass(TenantContext::class)]
#[CoversClass(DoctrineTenantActivation::class)]
#[CoversClass(ResolveActiveTenant::class)]
#[CoversClass(SwitchActiveTenant::class)]
#[CoversClass(CurrentTenantValueResolver::class)]
final class ActiveTenantRequestTest extends FunctionalTestCase
{
    public function testTheOnlyReachableTenantIsActive(): void
    {
        self::assertSame(['W-1'], $this->json($this->request('GET', '/invoices', ['X-Reachable-Tenants' => 'wholesale'])));
    }

    public function testTheTenantIsResolvedAfterTheFirewall(): void
    {
        self::assertSame(['W-1'], $this->json($this->request('GET', '/invoices', ['X-Account' => 'wholesale'])));
    }

    public function testTheRememberedChoiceIsActiveAmongSeveral(): void
    {
        $response = $this->request('GET', '/invoices', ['X-Reachable-Tenants' => 'retail,wholesale', 'X-Active-Tenant' => 'retail']);

        self::assertSame(['R-1', 'R-2'], $this->json($response));
    }

    /**
     * Resolution never guesses: with several tenants and no choice, the account is sent to the selector, with the
     * page it asked for when that page can be asked for again.
     */
    public function testSeveralTenantsWithoutAChoiceRequireASelection(): void
    {
        $response = $this->request('GET', '/invoices?page=2', ['X-Reachable-Tenants' => 'retail,wholesale']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/tenant/select?target=/invoices?page%3D2', $response->headers->get('Location'));
        self::assertSame('/tenant/select', $this->request('POST', '/invoices', ['X-Reachable-Tenants' => 'retail,wholesale'])->headers->get('Location'));
        self::assertSame(['retail', 'wholesale'], $this->json($this->request('GET', '/tenant/select', ['X-Reachable-Tenants' => 'retail,wholesale'])));
    }

    public function testAnIgnoredRouteIsReachedInNoTenant(): void
    {
        self::assertSame(['CAD', 'EUR'], $this->json($this->request('GET', '/currencies', ['X-Reachable-Tenants' => 'retail,wholesale'])));
    }

    /**
     * A revoked membership: the remembered choice is dropped and forgotten, never replaced by a neighbour unless it
     * is the only tenant left.
     */
    public function testAChoiceNoLongerReachableIsForgotten(): void
    {
        $response = $this->request('GET', '/invoices', ['X-Reachable-Tenants' => 'wholesale', 'X-Active-Tenant' => 'retail']);

        self::assertSame(['W-1'], $this->json($response));
        self::assertSame(1, $this->store()->forgotten);
    }

    /**
     * Fail closed: an account with no reachable tenant stays signed in and sees no tenant's rows, while the data
     * every tenant shares stays readable.
     */
    public function testNoReachableTenantSeesNoTenantScopedRow(): void
    {
        self::assertSame([], $this->json($this->request('GET', '/invoices')));
        self::assertSame(['CAD', 'EUR'], $this->json($this->request('GET', '/currencies')));
    }

    /**
     * The tenant of one request does not outlive it: the next request, by an account with no tenant, sees none.
     */
    public function testTheTenantIsLeftBetweenRequests(): void
    {
        self::assertSame(['W-1'], $this->json($this->request('GET', '/invoices', ['X-Reachable-Tenants' => 'wholesale'])));
        self::assertSame([], $this->json($this->request('GET', '/invoices')));
        self::assertNull($this->service(TenantContext::class, TenantContext::class)->current());
    }

    public function testTheSwitchRemembersAReachableTenantAndReturnsToTheTarget(): void
    {
        $response = $this->switch(['tenant' => 'wholesale', 'target' => '/invoices?page=2']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/invoices?page=2', $response->headers->get('Location'));
        self::assertSame('wholesale', $this->store()->remembered?->value);
        self::assertSame('/', $this->switch(['tenant' => 'retail'])->headers->get('Location'));
    }

    public function testTheSwitchRefusesATenantOutOfReach(): void
    {
        $this->switch(['tenant' => 'wholesale']);

        self::assertSame(403, $this->switch(['tenant' => 'outlet'])->getStatusCode());
        self::assertSame(403, $this->switch(['tenant' => ''])->getStatusCode());
        self::assertSame('wholesale', $this->store()->remembered?->value);
    }

    public function testTheSwitchIsPostedFromThisSiteOnly(): void
    {
        self::assertSame(403, $this->switch(['tenant' => 'retail'], ['Sec-Fetch-Site' => 'cross-site'])->getStatusCode());
        self::assertSame(403, $this->switch(['tenant' => 'retail'], ['Sec-Fetch-Site' => null, 'Origin' => 'https://elsewhere.example'])->getStatusCode());
        self::assertSame(302, $this->switch(['tenant' => 'retail'], ['Sec-Fetch-Site' => null, 'Origin' => 'http://localhost'])->getStatusCode());
        self::assertNotNull($this->store()->remembered);
    }

    /**
     * Only a path of this site is a target: anything a browser would read as another host goes to the root.
     */
    public function testTheSwitchNeverLeavesTheSite(): void
    {
        foreach (['//elsewhere.example/', '/\elsewhere.example', 'https://elsewhere.example/', 'invoices', ''] as $target) {
            self::assertSame('/', $this->switch(['tenant' => 'retail', 'target' => $target])->headers->get('Location'), $target);
        }
    }

    public function testTheCurrentTenantIsAControllerArgument(): void
    {
        self::assertSame(['retail'], $this->json($this->request('GET', '/current', ['X-Reachable-Tenants' => 'retail'])));
        self::assertSame(['retail'], $this->json($this->request('GET', '/optional', ['X-Reachable-Tenants' => 'retail'])));
        self::assertSame(403, $this->request('GET', '/current')->getStatusCode());
        self::assertSame([], $this->json($this->request('GET', '/optional')));
    }

    /**
     * The argument is the active tenant, never a request attribute of the same name, here a route default.
     */
    public function testTheCurrentTenantIsNotARequestAttribute(): void
    {
        self::assertSame(['retail'], $this->json($this->request('GET', '/optional-with-attribute', ['X-Reachable-Tenants' => 'retail'])));
    }

    /**
     * A post to the switch with several reachable tenants and the same origin.
     *
     * @param array<string, string>  $form
     * @param array<string, ?string> $headers
     */
    private function switch(array $form, array $headers = []): Response
    {
        $headers = array_filter([...['X-Reachable-Tenants' => 'retail,wholesale', 'Sec-Fetch-Site' => 'same-origin'], ...$headers], \is_string(...));

        return $this->request('POST', '/tenant/switch', $headers, $form);
    }

    private function store(): HeaderActiveTenantStore
    {
        return $this->service(HeaderActiveTenantStore::class, HeaderActiveTenantStore::class);
    }
}
