<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\FirewallComposer;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `iss` of the authorization response (RFC 9207), which ties a callback to the provider that issued it against the
 * mix-up attack. The `oidc_login` authenticator of Symfony, which the bundle configures, checks it before the code is
 * exchanged: these tests prove it through the bundle's firewall, so that a change of that authenticator is seen.
 */
#[CoversClass(FirewallComposer::class)]
final class IssuerParameterTest extends FunctionalTestCase
{
    public function testTheIssuerOfTheProviderSignsIn(): void
    {
        $this->boot();
        $this->provider()->announcesIssuerParameter = true;

        $this->returnFromProvider(FakeOidcProvider::ISSUER);

        self::assertSame('subject-1', $this->securityToken()?->getUserIdentifier());
    }

    public function testAnotherIssuerIsRefusedBeforeTheCodeIsExchanged(): void
    {
        $this->boot();

        $this->returnFromProvider('https://attacker.example');

        self::assertNull($this->securityToken());
        self::assertSame([], $this->provider()->tokenRequests);
    }

    public function testAnIssuerThatOnlyDiffersByATrailingSlashIsRefused(): void
    {
        $this->boot();
        $this->provider()->announcesIssuerParameter = true;

        $this->returnFromProvider(FakeOidcProvider::ISSUER.'/');

        self::assertNull($this->securityToken());
        self::assertSame([], $this->provider()->tokenRequests);
    }

    public function testAProviderThatAnnouncesTheParameterMustSendIt(): void
    {
        $this->boot();
        $this->provider()->announcesIssuerParameter = true;

        $this->returnFromProvider(null);

        self::assertNull($this->securityToken());
        self::assertSame([], $this->provider()->tokenRequests);
    }

    public function testAProviderThatDoesNotAnnounceItMayLeaveItOut(): void
    {
        $this->boot();

        $this->returnFromProvider(null);

        self::assertSame('subject-1', $this->securityToken()?->getUserIdentifier());
    }

    private function returnFromProvider(?string $issuer): Response
    {
        return $this->get($this->provider()->authorize($this->startSignIn(), [], $issuer));
    }
}
