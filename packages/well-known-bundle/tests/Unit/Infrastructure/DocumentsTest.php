<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Infrastructure;

use JsonException;
use Phil\WellKnownBundle\Application\Contract\NodeInfoProvider;
use Phil\WellKnownBundle\Application\Contract\SecurityTxtSigner;
use Phil\WellKnownBundle\Application\StaticWebFingerResolver;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;
use Phil\WellKnownBundle\Infrastructure\Http\Document\AtprotoDidDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\ChangePasswordDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\DocumentResponses;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDiscoveryDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\SecurityTxtDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\StaticDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\WebFingerDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(DocumentResponses::class)]
#[CoversClass(StaticDocument::class)]
#[CoversClass(SecurityTxtDocument::class)]
#[CoversClass(ChangePasswordDocument::class)]
#[CoversClass(WebFingerDocument::class)]
#[CoversClass(NodeInfoDiscoveryDocument::class)]
#[CoversClass(NodeInfoDocument::class)]
#[CoversClass(AtprotoDidDocument::class)]
final class DocumentsTest extends TestCase
{
    public function testAStaticDocument(): void
    {
        $response = new StaticDocument('{"a":1}', 'application/json', true)->serve(new Request());

        self::assertSame([200, '{"a":1}', 'application/json', '*'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('Content-Type'), $response->headers->get('Access-Control-Allow-Origin')]);
        self::assertFalse(new StaticDocument('x', 'text/plain')->serve(new Request())->headers->has('Access-Control-Allow-Origin'));
    }

    public function testJson(): void
    {
        self::assertSame('{"a":"é/"}', DocumentResponses::json(['a' => 'é/'], 'application/json', false)->getContent());
        $this->expectException(JsonException::class);
        DocumentResponses::json(\NAN, 'application/json', false);
    }

    public function testSecurityTxt(): void
    {
        $plain = new SecurityTxtDocument("Contact: mailto:a@b\n")->serve(new Request());
        self::assertSame(["Contact: mailto:a@b\n", 'text/plain; charset=utf-8', false], [$plain->getContent(), $plain->headers->get('Content-Type'), $plain->headers->has('Access-Control-Allow-Origin')]);

        $signer = new readonly class implements SecurityTxtSigner {
            public function sign(string $text): string
            {
                return '['.$text.']';
            }
        };
        self::assertSame("[Contact: mailto:a@b\n]", new SecurityTxtDocument("Contact: mailto:a@b\n", $signer)->serve(new Request())->getContent());
    }

    public function testChangePassword(): void
    {
        $toUrl = new ChangePasswordDocument('https://accounts.example.com/password')->serve(new Request());
        self::assertInstanceOf(RedirectResponse::class, $toUrl);
        self::assertSame([302, 'https://accounts.example.com/password'], [$toUrl->getStatusCode(), $toUrl->getTargetUrl()]);

        $toRoute = new ChangePasswordDocument('password', $this->urls(), ['tab' => 'security'])->serve(new Request());
        self::assertInstanceOf(RedirectResponse::class, $toRoute);
        self::assertSame([302, '/account/password?tab=security'], [$toRoute->getStatusCode(), $toRoute->getTargetUrl()]);
        self::assertSame('/account/password', $this->target(new ChangePasswordDocument('password', $this->urls())));
    }

    public function testWebFinger(): void
    {
        $document = new WebFingerDocument(new WebFinger([new StaticWebFingerResolver(['acct:a@b' => ['subject' => null, 'aliases' => [], 'properties' => [], 'links' => [
            ['rel' => 'self', 'type' => null, 'href' => 'https://b/a', 'template' => null, 'titles' => [], 'properties' => []],
            ['rel' => 'profile', 'type' => null, 'href' => 'https://b/@a', 'template' => null, 'titles' => [], 'properties' => []],
        ]]])]));

        $found = $document->serve(Request::create('https://b/.well-known/webfinger?resource=acct:a@b&rel=self&rel=self'));
        self::assertSame([200, '{"subject":"acct:a@b","links":[{"rel":"self","href":"https://b/a"}]}', 'application/jrd+json', '*'], [$found->getStatusCode(), $found->getContent(), $found->headers->get('Content-Type'), $found->headers->get('Access-Control-Allow-Origin')]);

        $unknown = $document->serve(Request::create('https://b/.well-known/webfinger?resource=acct:z@b'));
        self::assertSame([404, '', 'text/plain; charset=utf-8', '*'], [$unknown->getStatusCode(), $unknown->getContent(), $unknown->headers->get('Content-Type'), $unknown->headers->get('Access-Control-Allow-Origin')]);

        $bad = $document->serve(new Request());
        self::assertSame([400, '', '*'], [$bad->getStatusCode(), $bad->getContent(), $bad->headers->get('Access-Control-Allow-Origin')]);
    }

    public function testNodeInfoDiscovery(): void
    {
        $response = new NodeInfoDiscoveryDocument($this->urls(), 'nodeinfo')->serve(new Request());

        self::assertSame(['{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.1","href":"https://example.com/nodeinfo/2.1"}]}', 'application/json', '*'], [$response->getContent(), $response->headers->get('Content-Type'), $response->headers->get('Access-Control-Allow-Origin')]);
    }

    public function testNodeInfo(): void
    {
        $provider = new readonly class implements NodeInfoProvider {
            public function software(): NodeInfoSoftware
            {
                return new NodeInfoSoftware('site', '2.0');
            }

            public function usage(): NodeInfoUsage
            {
                return new NodeInfoUsage(3);
            }

            public function openRegistrations(): bool
            {
                return true;
            }

            public function metadata(): array
            {
                return ['k' => 'v'];
            }
        };

        $response = new NodeInfoDocument($provider, ['activitypub'], ['rss2.0'], ['smtp'])->serve(new Request());

        self::assertSame('{"version":"2.1","software":{"name":"site","version":"2.0"},"protocols":["activitypub"],"services":{"inbound":["rss2.0"],"outbound":["smtp"]},"openRegistrations":true,"usage":{"users":{"total":3}},"metadata":{"k":"v"}}', $response->getContent());
        self::assertSame(['application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"', '*'], [$response->headers->get('Content-Type'), $response->headers->get('Access-Control-Allow-Origin')]);
        self::assertStringContainsString('"services":{"inbound":[],"outbound":[]}', (string) new NodeInfoDocument($provider, ['activitypub'])->serve(new Request())->getContent());
    }

    public function testAtprotoDid(): void
    {
        $document = new AtprotoDidDocument('did:plc:site', ['alice.example.com' => 'did:plc:alice']);

        $site = $document->serve(Request::create('https://example.com/.well-known/atproto-did'));
        self::assertSame([200, 'did:plc:site', 'text/plain; charset=utf-8', '*'], [$site->getStatusCode(), $site->getContent(), $site->headers->get('Content-Type'), $site->headers->get('Access-Control-Allow-Origin')]);
        self::assertSame('did:plc:alice', $document->serve(Request::create('https://ALICE.example.com/.well-known/atproto-did'))->getContent());

        $none = new AtprotoDidDocument(null, ['alice.example.com' => 'did:plc:alice'])->serve(Request::create('https://bob.example.com/.well-known/atproto-did'));
        self::assertSame([404, '', 'text/plain; charset=utf-8', '*'], [$none->getStatusCode(), $none->getContent(), $none->headers->get('Content-Type'), $none->headers->get('Access-Control-Allow-Origin')]);
        self::assertSame('did:plc:site', new AtprotoDidDocument('did:plc:site')->serve(Request::create('https://example.com/'))->getContent());
    }

    private function target(ChangePasswordDocument $document): string
    {
        $response = $document->serve(new Request());
        self::assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('password', new Route('/account/password'));
        $routes->add('nodeinfo', new Route('/nodeinfo/2.1'));

        return new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https'));
    }
}
