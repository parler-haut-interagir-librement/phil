<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Override;
use Phil\TenancyBundle\Infrastructure\Session\SessionActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The choice remembered in the application's session, through a browser that keeps its cookie: chosen once on
 * the switch, active on the next pages, forgotten when the membership is revoked.
 */
#[CoversClass(SessionActiveTenantStore::class)]
final class SessionStoreTest extends FunctionalTestCase
{
    public function testTheChoiceLastsAsLongAsTheSession(): void
    {
        $browser = $this->browser();

        $browser->request('GET', '/invoices', server: ['HTTP_X_REACHABLE_TENANTS' => 'retail,wholesale']);
        self::assertSame(302, $browser->getResponse()->getStatusCode());

        $browser->request('POST', '/tenant/switch', ['tenant' => 'retail', 'target' => '/invoices'], server: ['HTTP_X_REACHABLE_TENANTS' => 'retail,wholesale', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertSame(302, $browser->getResponse()->getStatusCode());

        $browser->request('GET', '/invoices', server: ['HTTP_X_REACHABLE_TENANTS' => 'retail,wholesale']);
        self::assertSame(['R-1', 'R-2'], $this->json($browser->getResponse()));

        // The membership of retail is revoked: wholesale is the only tenant left, and the choice is forgotten.
        $browser->request('GET', '/invoices', server: ['HTTP_X_REACHABLE_TENANTS' => 'wholesale']);
        self::assertSame(['W-1'], $this->json($browser->getResponse()));

        $browser->request('GET', '/invoices', server: ['HTTP_X_REACHABLE_TENANTS' => 'retail,wholesale']);
        self::assertSame(302, $browser->getResponse()->getStatusCode());
    }

    #[Override]
    protected static function configuration(): array
    {
        return ['store' => 'session'];
    }

    private function browser(): KernelBrowser
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $browser = new KernelBrowser($kernel);
        $browser->disableReboot();

        return $browser;
    }
}
