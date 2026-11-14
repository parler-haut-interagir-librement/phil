<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Override;
use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use Phil\TenancyBundle\Infrastructure\Http\RedirectHostAlias;
use Phil\TenancyBundle\Infrastructure\Security\TenantRequestMatcher;
use Phil\TenancyBundle\Infrastructure\Security\TenantUserProviders;
use Phil\TenancyBundle\Tests\Functional\Members\MembersKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Members of each tenant in one application: each club's host, or its alias, enters the club before the firewall;
 * a club can sign its members in with its own firewall, and each club loads its accounts from its own provider.
 */
#[CoversClass(RedirectHostAlias::class)]
#[CoversClass(ConfiguredHostTenants::class)]
#[CoversClass(TenantRequestMatcher::class)]
#[CoversClass(TenantUserProviders::class)]
final class MembersTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return MembersKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testAnAliasIsSentToItsHost(): void
    {
        $browser = self::createClient();

        $browser->request('GET', 'http://www.club-a.test/whoami?page=2');
        self::assertResponseRedirects('http://club-a.test/whoami?page=2', 301);

        $browser->request('POST', 'http://www.club-a.test/whoami');
        self::assertResponseRedirects('http://club-a.test/whoami', 308);
    }

    public function testEachClubSignsInItsOwnMembers(): void
    {
        $browser = self::createClient();

        self::assertSame(['account' => 'ana', 'firewall' => 'main', 'tenant' => 'a'], $this->json($browser, 'club-a.test', 'ana'));
        self::assertSame(['account' => 'ben', 'firewall' => 'club_b', 'tenant' => 'b'], $this->json($browser, 'club-b.test', 'ben'));

        $browser->request('GET', 'http://club-a.test/whoami', server: ['HTTP_X_LOGIN' => 'ben']);
        self::assertResponseStatusCodeSame(401);
        $browser->request('GET', 'http://club-b.test/whoami', server: ['HTTP_X_LOGIN' => 'ana']);
        self::assertResponseStatusCodeSame(401);
        $browser->request('GET', 'http://club-b.test/whoami', server: ['HTTP_X_LOGIN' => 'sam']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testARequestInNoClubSignsInTheStaff(): void
    {
        $browser = self::createClient();

        self::assertSame(['account' => 'sam', 'firewall' => 'main', 'tenant' => null], $this->json($browser, 'central.test', 'sam'));

        $browser->request('GET', 'http://central.test/whoami', server: ['HTTP_X_LOGIN' => 'ana']);
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(KernelBrowser $browser, string $host, string $login): array
    {
        $browser->request('GET', 'http://'.$host.'/whoami', server: ['HTTP_X_LOGIN' => $login]);
        self::assertResponseIsSuccessful();
        $decoded = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
