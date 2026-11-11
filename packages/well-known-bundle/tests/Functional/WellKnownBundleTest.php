<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional;

use Override;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\WellKnownBundle\PhilWellKnownBundle;
use Phil\WellKnownBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(PhilWellKnownBundle::class)]
final class WellKnownBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function documents(): iterable
    {
        yield 'security.txt' => ['/.well-known/security.txt', 'text/plain; charset=utf-8', false];
        yield 'nodeinfo' => ['/.well-known/nodeinfo', 'application/json', true];
        yield 'nodeinfo 2.1' => ['/nodeinfo/2.1', 'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"', true];
        yield 'did.json' => ['/.well-known/did.json', 'application/did+ld+json', true];
        yield 'did-configuration.json' => ['/.well-known/did-configuration.json', 'application/json', true];
        yield 'atproto-did' => ['/.well-known/atproto-did', 'text/plain; charset=utf-8', true];
        yield 'apple-app-site-association' => ['/.well-known/apple-app-site-association', 'application/json', false];
        yield 'assetlinks.json' => ['/.well-known/assetlinks.json', 'application/json', false];
        yield 'matrix/server' => ['/.well-known/matrix/server', 'application/json', true];
        yield 'traffic-advice' => ['/.well-known/traffic-advice', 'application/trafficadvice+json', false];
        yield 'matrix/client' => ['/.well-known/matrix/client', 'application/json', false];
    }

    /**
     * Every document has its media type, may be cached, is not indexed, and starts no session.
     */
    #[DataProvider('documents')]
    public function testADocumentIsServed(string $path, string $contentType, bool $cors): void
    {
        $client = $this->client();

        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertSame($contentType, $response->headers->get('Content-Type'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertSame([true, 600], [$response->headers->hasCacheControlDirective('public'), $response->getMaxAge()]);
        self::assertSame($cors ? '*' : null, $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame([], $response->headers->getCookies());
        self::assertNotNull($response->getEtag());

        $client->request('GET', $path, server: ['HTTP_IF_NONE_MATCH' => $response->getEtag()]);
        self::assertResponseStatusCodeSame(304);

        $client->request('HEAD', $path);
        self::assertResponseIsSuccessful();
        self::assertSame('', (string) $client->getResponse()->getContent());
    }

    public function testTheBodies(): void
    {
        $client = $this->client();

        self::assertSame(
            "-----BEGIN PGP SIGNED MESSAGE-----\nHash: SHA512\n\nContact: mailto:security@example.com\nContact: https://example.com/security\nExpires: 2099-12-31T00:00:00Z\nEncryption: https://example.com/pgp-key.txt\nPreferred-Languages: fr, en\nCanonical: https://example.com/.well-known/security.txt\nPolicy: https://example.com/disclosure\n-----BEGIN PGP SIGNATURE-----\n\nfake\n-----END PGP SIGNATURE-----\n",
            $this->body($client, '/.well-known/security.txt'),
        );
        self::assertSame('{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.1","href":"https://example.com/nodeinfo/2.1"}]}', $this->body($client, '/.well-known/nodeinfo'));
        self::assertSame(
            '{"version":"2.1","software":{"name":"example","version":"1.2.3","repository":"https://example.com/code"},"protocols":["activitypub"],"services":{"inbound":[],"outbound":["rss2.0"]},"openRegistrations":true,"usage":{"users":{"total":42,"activeMonth":7},"localPosts":1000},"metadata":{"nodeName":"Example"}}',
            $this->body($client, '/nodeinfo/2.1'),
        );
        self::assertSame('{"@context":["https://www.w3.org/ns/did/v1"],"id":"did:web:example.com","verificationMethod":[]}', $this->body($client, '/.well-known/did.json'));
        self::assertSame(
            '{"@context":"https://identity.foundation/.well-known/did-configuration/v1","linked_dids":[{"@context":["https://www.w3.org/2018/credentials/v1"],"type":["VerifiableCredential","DomainLinkageCredential"]},"eyJhbGciOiJFZERTQSJ9.eyJpc3MiOiJkaWQ6d2ViOmV4YW1wbGUuY29tIn0.c2lnbmF0dXJl"]}',
            $this->body($client, '/.well-known/did-configuration.json'),
        );
        self::assertSame('did:plc:ewvi7nxzyoun6zhxrhs64oiz', $this->body($client, '/.well-known/atproto-did'));
        self::assertSame('{"applinks":{"details":[{"appIDs":["ABCDE12345.com.example.app"],"components":[{"/":"/events/*"}]}]}}', $this->body($client, '/.well-known/apple-app-site-association'));
        self::assertSame('[{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"com.example.app","sha256_cert_fingerprints":["'.implode(':', array_fill(0, 32, 'AB')).'"]}}]', $this->body($client, '/.well-known/assetlinks.json'));
        self::assertSame('{"m.server": "matrix.example.com:443"}'."\n", $this->body($client, '/.well-known/matrix/server'));

        $client->setServerParameter('HTTP_HOST', 'Alice.example.com');
        self::assertSame('did:plc:alice', $this->body($client, '/.well-known/atproto-did'));
    }

    public function testChangePasswordRedirects(): void
    {
        $client = $this->client();

        $client->request('GET', '/.well-known/change-password');

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/account/password?tab=security');
        self::assertSame('noindex', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    public function testWebFinger(): void
    {
        $client = $this->client();

        $client->request('GET', '/.well-known/webfinger?resource=acct%3Asite%40example.com');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/jrd+json');
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        self::assertSame(
            '{"subject":"acct:site@example.com","aliases":["https://example.com/"],"properties":{"http://schema.org/name":"Example"},"links":[{"rel":"http://webfinger.net/rel/profile-page","type":"text/html","href":"https://example.com/","titles":{"en":"Home"}},{"rel":"self","type":"application/activity+json","href":"https://example.com/actor"}]}',
            (string) $client->getResponse()->getContent(),
        );

        $client->request('GET', '/.well-known/webfinger?resource=acct:alice@example.com&rel=self&rel=http%3A%2F%2Fwebfinger.net%2Frel%2Favatar');
        self::assertSame('{"subject":"acct:alice@example.com","aliases":["https://example.com/@alice"],"links":[{"rel":"self","type":"application/activity+json","href":"https://example.com/users/alice"}]}', (string) $client->getResponse()->getContent());

        $client->request('GET', '/.well-known/webfinger?resource=acct:site@example.com&rel=self');
        self::assertSame('{"subject":"acct:site@example.com","aliases":["https://example.com/"],"properties":{"http://schema.org/name":"Example"},"links":[{"rel":"self","type":"application/activity+json","href":"https://example.com/actor"}]}', (string) $client->getResponse()->getContent());

        foreach (['acct:bob@example.com', 'mailto:alice@example.com', 'https://example.com/@alice'] as $unknown) {
            $client->request('GET', '/.well-known/webfinger?resource='.rawurlencode($unknown));
            self::assertResponseStatusCodeSame(404);
            self::assertSame('', (string) $client->getResponse()->getContent());
            self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        }

        foreach (['', '?resource=', '?resource=alice', '?resource=acct:a@b&resource=acct:c@d'] as $bad) {
            $client->request('GET', '/.well-known/webfinger'.$bad);
            self::assertResponseStatusCodeSame(400);
            self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('public'));
        }
    }

    public function testAnotherMethodIsRefused(): void
    {
        $client = $this->client();

        $client->request('POST', '/.well-known/security.txt');

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function paths(): iterable
    {
        foreach (self::documents() as $name => [$path]) {
            yield $name => [$path];
        }

        yield 'change-password' => ['/.well-known/change-password'];
        yield 'webfinger' => ['/.well-known/webfinger?resource=acct:site@example.com'];
    }

    #[DataProvider('paths')]
    public function testADocumentTurnedOffIsNotFound(string $path): void
    {
        $client = self::createClient(['environment' => 'off'], ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * With phil/monitoring-bundle installed, the expiry of the security.txt is one of the checks of the site.
     */
    public function testTheExpiryIsCheckedByTheMonitoring(): void
    {
        self::bootKernel();
        $checker = self::getContainer()->get(HealthChecker::class);
        self::assertInstanceOf(HealthChecker::class, $checker);

        $result = array_find($checker->report()->results, static fn (CheckResult $result): bool => 'security_txt' === $result->name);

        self::assertInstanceOf(CheckResult::class, $result);
        self::assertSame([HealthStatus::Up, 'Expires on 2099-12-31T00:00:00+00:00.'], [$result->status(), $result->outcome->summary]);
    }

    public function testNoExpiryIsCheckedWithoutTheSecurityTxt(): void
    {
        self::bootKernel(['environment' => 'off']);
        $checker = self::getContainer()->get(HealthChecker::class);
        self::assertInstanceOf(HealthChecker::class, $checker);

        self::assertSame([], array_map(static fn (CheckResult $result): string => $result->name, $checker->report()->results));
    }

    private function client(): KernelBrowser
    {
        return self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
    }

    private function body(KernelBrowser $client, string $path): string
    {
        $client->request('GET', $path);
        self::assertResponseIsSuccessful($path);

        return (string) $client->getResponse()->getContent();
    }
}
