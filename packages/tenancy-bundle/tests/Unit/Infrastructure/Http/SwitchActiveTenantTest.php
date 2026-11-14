<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Override;
use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Http\SwitchActiveTenant;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\FixedReachableTenants;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\InMemoryActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(SwitchActiveTenant::class)]
final class SwitchActiveTenantTest extends TestCase
{
    private InMemoryActiveTenantStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryActiveTenantStore();
    }

    public function testAReachableTenantIsRememberedAndTheTargetFollowed(): void
    {
        $response = $this->switch()($this->post(['tenant' => 'wholesale', 'target' => '/invoices?page=2']));

        self::assertSame('/invoices?page=2', $response->getTargetUrl());
        self::assertSame('wholesale', $this->store->remembered()?->value);
        self::assertSame('phil_tenancy_switch', SwitchActiveTenant::ROUTE);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function targetsElsewhere(): iterable
    {
        yield 'protocol-relative' => ['//elsewhere.example/'];
        yield 'backslash' => ['/\elsewhere.example'];
        yield 'absolute' => ['https://elsewhere.example/'];
        yield 'relative' => ['invoices'];
        yield 'missing' => [''];
        yield 'later slash' => ['x/invoices'];
        yield 'tab before a slash' => ["/\t/elsewhere.example"];
        yield 'line feed before a slash' => ["/\n/elsewhere.example"];
        yield 'carriage return before a backslash' => ["/\r\\elsewhere.example"];
        yield 'tab later on' => ["/invoices\t"];
        yield 'space' => ['/ /elsewhere.example'];
        yield 'no-break space' => ["/\u{a0}/elsewhere.example"];
        yield 'nul' => ["/invoices\0"];
        yield 'delete' => ["/\x7F/elsewhere.example"];
        yield 'invalid UTF-8' => ["/invoices\xFF"];
    }

    public function testAnEncodedTargetIsFollowedAsItIs(): void
    {
        $response = $this->switch()($this->post(['tenant' => 'retail', 'target' => '/%09/search?q=caf%C3%A9&tag=é']));

        self::assertSame('/%09/search?q=caf%C3%A9&tag=é', $response->getTargetUrl());
    }

    #[DataProvider('targetsElsewhere')]
    public function testATargetElsewhereGoesToTheRootOfTheSite(string $target): void
    {
        $request = $this->post(['tenant' => 'retail', 'target' => $target], server: ['SCRIPT_FILENAME' => '/srv/app/index.php', 'SCRIPT_NAME' => '/app/index.php', 'REQUEST_URI' => '/app/tenant/switch']);

        self::assertSame('/app/', $this->switch()($request)->getTargetUrl());
    }

    public function testAnUnreachableTenantIsRefusedAndTheChoiceKept(): void
    {
        $this->switch()($this->post(['tenant' => 'retail']));

        foreach (['outlet', '', str_repeat('x', 256)] as $tenant) {
            try {
                $this->switch()($this->post(['tenant' => $tenant]));
                self::fail($tenant.' was accepted.');
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('This tenant is not reachable.', $accessDeniedHttpException->getMessage());
            }
        }

        self::assertSame('retail', $this->store->remembered()?->value);
    }

    public function testTheFormIsAcceptedFromThisSiteOnly(): void
    {
        self::assertSame('/', $this->switch()($this->post(['tenant' => 'retail'], ['Sec-Fetch-Site' => null, 'Origin' => 'https://shop.example']))->getTargetUrl());

        foreach ([['Sec-Fetch-Site' => 'same-site'], ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://shop.example'], ['Sec-Fetch-Site' => null, 'Origin' => 'https://elsewhere.example'], ['Sec-Fetch-Site' => null]] as $headers) {
            try {
                $this->switch()($this->post(['tenant' => 'wholesale'], $headers));
                self::fail(json_encode($headers, \JSON_THROW_ON_ERROR).' was accepted.');
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The form was not posted from this site.', $accessDeniedHttpException->getMessage());
            }
        }

        self::assertSame('retail', $this->store->remembered()?->value);
    }

    private function switch(): SwitchActiveTenant
    {
        return new SwitchActiveTenant(new ActiveTenant($this->store, new FixedReachableTenants(ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale')))));
    }

    /**
     * @param array<string, string>  $form
     * @param array<string, ?string> $headers
     * @param array<string, string>  $server
     */
    private function post(array $form, array $headers = [], array $server = []): Request
    {
        $request = Request::create('https://shop.example/tenant/switch', 'POST', $form);
        // Read lazily: set after `create()`, which writes its own.
        $request->server->add($server);
        $request->headers->add(array_filter([...['Sec-Fetch-Site' => 'same-origin'], ...$headers], \is_string(...)));

        return $request;
    }
}
