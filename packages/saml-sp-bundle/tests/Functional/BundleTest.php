<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Functional;

use DateTimeImmutable;
use Override;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Signature\SignatureAlgorithm;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\PhilSamlSpBundle;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use Phil\SamlSpBundle\Tests\Functional\App\Refusals;
use Phil\SamlSpBundle\Tests\Functional\App\TestKernel;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(PhilSamlSpBundle::class)]
final class BundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheMetadataOfTheServiceProviderIsServed(): void
    {
        $client = $this->browser();
        $client->request('GET', '/saml/acme/metadata');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/samlmetadata+xml');
        $xml = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('entityID="https://app.example.com/saml/acme/metadata"', $xml);
        self::assertStringContainsString('Location="https://app.example.com/saml/acme/acs"', $xml);
        self::assertStringContainsString('Location="https://app.example.com/saml/acme/slo"', $xml);

        $client->request('GET', '/saml/unknown/metadata');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAPersonSignsInAndOut(): void
    {
        $client = $this->browser();
        $client->request('GET', '/saml/acme/login?target=/dashboard%3Ftab%3D2');

        $location = (string) $client->getResponse()->headers->get('Location');

        self::assertResponseStatusCodeSame(302);
        self::assertStringStartsWith(Saml::SSO.'?SAMLRequest=', $location);
        self::assertNotNull(Saml::query($location, 'Signature'));
        self::assertNotNull($client->getCookieJar()->get(SamlAuthenticator::COOKIE));
        $request = Saml::load(Saml::inflate($location))->documentElement;
        self::assertNotNull($request);
        $id = $request->getAttribute('ID');
        self::assertSame($id, Saml::query($location, 'RelayState'));

        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => Saml::response($id, new DateTimeImmutable()), 'RelayState' => $id]);
        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/dashboard?tab=2');

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSame('acme|'.Saml::OBJECT_ID.' ROLE_USER,ROLE_TEACHER', $client->getResponse()->getContent());

        $client->request('GET', '/logout');
        $logout = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(Saml::IDP_SLO.'?SAMLRequest=', $logout);
        $logoutId = Saml::load(Saml::inflate($logout))->documentElement?->getAttribute('ID');
        self::assertIsString($logoutId);
        self::assertStringContainsString('ada.lovelace@example.org', Saml::inflate($logout));

        $client->request('GET', '/dashboard');
        self::assertResponseStatusCodeSame(401);

        $answer = HttpRedirect::url(Url::fromString(Saml::BASE.'/slo'), 'SAMLResponse', Saml::logoutResponse(new DateTimeImmutable(), $logoutId), $logoutId, Saml::pair('entra')->privateKey, SignatureAlgorithm::RsaSha256);
        $client->request('GET', '/saml/acme/slo?'.parse_url($answer, \PHP_URL_QUERY));
        self::assertResponseRedirects('/');
    }

    public function testAResponseIsAcceptedOnce(): void
    {
        $client = $this->browser();
        $client->request('GET', '/saml/acme/login');

        $id = (string) Saml::query((string) $client->getResponse()->headers->get('Location'), 'RelayState');
        $response = Saml::response($id, new DateTimeImmutable());
        $cookie = $client->getCookieJar()->get(SamlAuthenticator::COOKIE);
        self::assertNotNull($cookie);

        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => $response, 'RelayState' => $id]);
        self::assertSame([], $this->refusals($client)->reasons);
        self::assertResponseRedirects('/');

        $client->getCookieJar()->set($cookie);
        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => $response, 'RelayState' => $id]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('The sign-in through the identity provider was refused.', $client->getResponse()->getContent());
    }

    public function testAResponseCarriedByAnotherBrowserIsRefused(): void
    {
        $client = $this->browser();
        $client->request('GET', '/saml/acme/login');

        $id = (string) Saml::query((string) $client->getResponse()->headers->get('Location'), 'RelayState');
        $client->getCookieJar()->set(new Cookie(SamlAuthenticator::COOKIE, 'another-browser', null, '/', 'app.example.com'));

        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => Saml::response($id, new DateTimeImmutable()), 'RelayState' => $id]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnsolicitedResponseNeedsTheIdentityProviderToAllowIt(): void
    {
        $client = $this->browser();
        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => Saml::response(null, new DateTimeImmutable())]);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => Saml::response(null, new DateTimeImmutable()), 'RelayState' => '/dashboard']);
        self::assertResponseStatusCodeSame(403, 'a response sent to the services of acme');

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => Saml::response(null, new DateTimeImmutable(), base: 'https://app.example.com/saml/portal'), 'RelayState' => 'https://evil.example.org/']);
        self::assertResponseRedirects('/');

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => Saml::response(null, new DateTimeImmutable(), base: 'https://app.example.com/saml/portal'), 'RelayState' => '/dashboard']);
        self::assertResponseRedirects('/dashboard');
        self::assertSame(['acme: The response answers no request of this browser, and the identity provider may not send one unsolicited.', 'portal: The destination of the response is not the assertion consumer service.'], $this->refusals($client)->reasons);
    }

    public function testTheAssertionConsumerServiceWithoutAResponse(): void
    {
        $client = $this->browser();
        $client->request('POST', '/saml/acme/acs');

        self::assertResponseStatusCodeSame(400);
    }

    public function testTheIdentityProviderSignsThePersonOut(): void
    {
        $client = $this->browser();
        $client->request('GET', '/saml/acme/login');

        $id = (string) Saml::query((string) $client->getResponse()->headers->get('Location'), 'RelayState');
        $client->request('POST', '/saml/acme/acs', ['SAMLResponse' => Saml::response($id, new DateTimeImmutable()), 'RelayState' => $id]);
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $request = HttpRedirect::url(Url::fromString(Saml::BASE.'/slo'), 'SAMLRequest', Saml::logoutRequest(new DateTimeImmutable(), sign: false), 'relay', Saml::pair('entra')->privateKey);
        $client->request('GET', '/saml/acme/slo?'.parse_url($request, \PHP_URL_QUERY));
        $answer = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(Saml::IDP_SLO.'?SAMLResponse=', $answer);
        self::assertSame('relay', Saml::query($answer, 'RelayState'));
        self::assertStringContainsString('urn:oasis:names:tc:SAML:2.0:status:Success', Saml::inflate($answer, 'SAMLResponse'));

        $client->request('GET', '/dashboard');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnUnsignedLogoutIsRefused(): void
    {
        $client = $this->browser();
        $client->request('POST', '/saml/acme/slo', ['SAMLRequest' => base64_encode(Saml::logoutRequest(new DateTimeImmutable(), sign: false))]);

        self::assertResponseStatusCodeSame(400);
    }

    private function refusals(KernelBrowser $client): Refusals
    {
        $refusals = $client->getContainer()->get(Refusals::class);
        self::assertInstanceOf(Refusals::class, $refusals);

        return $refusals;
    }

    private function browser(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->setServerParameter('HTTPS', 'on');
        $client->setServerParameter('HTTP_HOST', 'app.example.com');

        return $client;
    }
}
