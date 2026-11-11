<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\WellKnownBundle\Application\Attribute\AsWellKnownDocument;
use Phil\WellKnownBundle\Application\ConfiguredNodeInfoProvider;
use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Application\StaticWebFingerResolver;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Infrastructure\DependencyInjection\WellKnownPass;
use Phil\WellKnownBundle\Infrastructure\DependencyInjection\WellKnownServices;
use Phil\WellKnownBundle\Infrastructure\Http\Document\AtprotoDidDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\ChangePasswordDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDiscoveryDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\SecurityTxtDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\StaticDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\WebFingerDocument;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Phil\WellKnownBundle\Infrastructure\Monitoring\SecurityTxtExpiryCheck;
use Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader;
use Phil\WellKnownBundle\PhilWellKnownBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilWellKnownBundle::class)]
#[CoversClass(WellKnownServices::class)]
#[CoversClass(WellKnownPass::class)]
final class PhilWellKnownBundleTest extends TestCase
{
    private const string FINGERPRINT = 'AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89';

    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-well-known-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_well_known', $this->extension()->getAlias());
        self::assertSame(['service_container', WellKnownController::class, WellKnownRouteLoader::class], array_keys($container->getDefinitions()));
        self::assertSame([null, 3600], $container->getDefinition(WellKnownController::class)->getArguments());
        self::assertTrue($container->getDefinition(WellKnownController::class)->isPublic());
        self::assertTrue($container->getDefinition(WellKnownController::class)->hasTag('controller.service_arguments'));
        self::assertSame([[]], $container->getDefinition(WellKnownRouteLoader::class)->getArguments());
        self::assertTrue($container->getDefinition(WellKnownRouteLoader::class)->hasTag('routing.loader'));
        self::assertSame([['after' => StaticWebFingerResolver::class]], $container->getAutoconfiguredInstanceof()[WebFingerResolver::class]->getTag('phil_well_known.webfinger_resolver'));

        new WellKnownPass()->process($container);
        self::assertSame([], $container->getParameter('phil_well_known.routes'));
        self::assertSame([[]], $container->getDefinition(WellKnownRouteLoader::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(WellKnownController::class)->getArgument(0));
        self::assertSame(['phil_well_known.document', 'phil_well_known.webfinger_resolver', 'phil_well_known_nodeinfo_document'], [WellKnownServices::DOCUMENT_TAG, WellKnownServices::RESOLVER_TAG, WellKnownServices::NODEINFO_ROUTE]);
    }

    public function testTheAttributeTagsADocument(): void
    {
        $container = $this->load([]);
        $configurator = $container->getAttributeAutoconfigurators()[AsWellKnownDocument::class][0];
        self::assertInstanceOf(Closure::class, $configurator);

        $definition = new ChildDefinition('');
        $configurator($definition, new AsWellKnownDocument('matrix/client'));
        $configurator($definition, new AsWellKnownDocument('/x', 'x_route'));

        self::assertSame([['path' => 'matrix/client'], ['path' => '/x', 'route' => 'x_route']], $definition->getTag('phil_well_known.document'));
    }

    public function testEveryDocument(): void
    {
        file_put_contents($this->directory.'/did.json', '{"id": "did:web:example.com"}');
        file_put_contents($this->directory.'/aasa.json', '{"webcredentials": {"apps": ["A.b.c"]}}');
        file_put_contents($this->directory.'/credential.jwt', "  aaa.bbb.ccc\n");
        file_put_contents($this->directory.'/credential.json', '{"type": ["VerifiableCredential"]}');
        file_put_contents($this->directory.'/body.txt', 'from a file');

        $container = $this->load([
            'cache_max_age' => 0,
            'security_txt' => ['contact' => ['mailto:s@example.com'], 'expires' => '2099-01-01', 'signer' => 'app.signer', 'hiring' => ['https://example.com/jobs']],
            'change_password' => ['url' => '/account/password'],
            'webfinger' => ['resources' => ['acct:Me@My-Site.example' => ['properties' => ['http://e.x/c-d' => 'v'], 'links' => [['rel' => 'self', 'href' => 'https://example.com/me', 'titles' => ['fr-CA' => 'Moi'], 'properties' => ['http://e.x/a-b' => null]]]]]],
            'nodeinfo' => ['path' => '/node-info', 'software' => ['name' => 'site', 'version' => '2.0', 'homepage' => 'https://example.com'], 'protocols' => ['activitypub', 'diaspora'], 'services' => ['inbound' => ['rss2.0'], 'outbound' => ['smtp']], 'open_registrations' => true, 'metadata' => ['nodeName' => 'Site']],
            'did' => ['file' => $this->directory.'/did.json'],
            'did_configuration' => ['linked_dids' => ['x.y.z'], 'files' => [$this->directory.'/credential.jwt', $this->directory.'/credential.json']],
            'atproto' => ['hosts' => ['Alice.My-Site.com' => 'did:plc:alice']],
            'apple_app_site_association' => ['file' => $this->directory.'/aasa.json'],
            'asset_links' => ['android_apps' => [['package_name' => 'com.example.app', 'sha256_cert_fingerprints' => [self::FINGERPRINT], 'relations' => ['delegate_permission/common.get_login_creds']]], 'statements' => [['relation' => ['x'], 'target' => ['namespace' => 'web', 'site' => 'https://example.com']], ['relation' => ['y']]]],
            'documents' => ['traffic-advice' => ['content' => '[]', 'content_type' => 'application/trafficadvice+json'], 'a/b' => ['file' => $this->directory.'/body.txt', 'content_type' => 'text/plain', 'cors' => true]],
        ]);

        self::assertSame(0, $container->getDefinition(WellKnownController::class)->getArgument(1));
        self::assertEquals(
            [SecurityTxtDocument::class, ["Contact: mailto:s@example.com\nExpires: 2099-01-01T00:00:00Z\nHiring: https://example.com/jobs\n", new Reference('app.signer')]],
            $this->document($container, 'security.txt'),
        );
        self::assertSame([ChangePasswordDocument::class, ['/account/password']], $this->document($container, 'change-password'));
        self::assertEquals([WebFingerDocument::class, [new Reference(WebFinger::class)]], $this->document($container, 'webfinger'));
        self::assertEquals([new TaggedIteratorArgument('phil_well_known.webfinger_resolver')], $container->getDefinition(WebFinger::class)->getArguments());
        self::assertSame([['acct:Me@my-site.example' => ['properties' => ['http://e.x/c-d' => 'v'], 'links' => [['rel' => 'self', 'href' => 'https://example.com/me', 'titles' => ['fr-CA' => 'Moi'], 'properties' => ['http://e.x/a-b' => null], 'type' => null, 'template' => null]], 'subject' => null, 'aliases' => []]]], $container->getDefinition(StaticWebFingerResolver::class)->getArguments());
        self::assertTrue($container->getDefinition(StaticWebFingerResolver::class)->hasTag('phil_well_known.webfinger_resolver'));
        self::assertEquals([NodeInfoDiscoveryDocument::class, [new Reference('router'), 'phil_well_known_nodeinfo_document']], $this->document($container, 'nodeinfo'));
        self::assertEquals([NodeInfoDocument::class, [new Reference(ConfiguredNodeInfoProvider::class), ['activitypub', 'diaspora'], ['rss2.0'], ['smtp']]], $this->document($container, '/node-info'));
        self::assertEquals([new Definition(NodeInfoSoftware::class, ['site', '2.0', null, 'https://example.com']), true, ['nodeName' => 'Site']], $container->getDefinition(ConfiguredNodeInfoProvider::class)->getArguments());
        self::assertSame([['path' => '/node-info', 'route' => 'phil_well_known_nodeinfo_document']], $container->getDefinition('phil_well_known.document./node-info')->getTag('phil_well_known.document'));
        self::assertSame([['path' => 'nodeinfo']], $container->getDefinition('phil_well_known.document.nodeinfo')->getTag('phil_well_known.document'));
        self::assertSame([StaticDocument::class, ['{"id":"did:web:example.com"}', 'application/did+json', true]], $this->document($container, 'did.json'));
        self::assertSame([StaticDocument::class, ['{"@context":"https://identity.foundation/.well-known/did-configuration/v1","linked_dids":["x.y.z","aaa.bbb.ccc",{"type":["VerifiableCredential"]}]}', 'application/json', true]], $this->document($container, 'did-configuration.json'));
        self::assertSame([AtprotoDidDocument::class, [null, ['alice.my-site.com' => 'did:plc:alice']]], $this->document($container, 'atproto-did'));
        self::assertSame([StaticDocument::class, ['{"webcredentials":{"apps":["A.b.c"]}}', 'application/json', false]], $this->document($container, 'apple-app-site-association'));
        self::assertSame([StaticDocument::class, ['[{"relation":["delegate_permission/common.get_login_creds"],"target":{"namespace":"android_app","package_name":"com.example.app","sha256_cert_fingerprints":["'.self::FINGERPRINT.'"]}},{"relation":["x"],"target":{"namespace":"web","site":"https://example.com"}},{"relation":["y"]}]', 'application/json', false]], $this->document($container, 'assetlinks.json'));
        self::assertSame([StaticDocument::class, ['[]', 'application/trafficadvice+json', false]], $this->document($container, 'traffic-advice'));
        self::assertSame([StaticDocument::class, ['from a file', 'text/plain', true]], $this->document($container, 'a/b'));

        $resources = array_map(static fn (object $resource): string => (string) $resource, array_filter($container->getResources(), static fn (object $resource): bool => $resource instanceof FileResource));
        foreach (['did.json', 'aasa.json', 'credential.jwt', 'credential.json', 'body.txt'] as $file) {
            self::assertContains((string) realpath($this->directory.'/'.$file), $resources);
        }

        new WellKnownPass()->process($container);
        self::assertSame([
            'phil_well_known_a_b' => '/.well-known/a/b',
            'phil_well_known_apple_app_site_association' => '/.well-known/apple-app-site-association',
            'phil_well_known_assetlinks_json' => '/.well-known/assetlinks.json',
            'phil_well_known_atproto_did' => '/.well-known/atproto-did',
            'phil_well_known_change_password' => '/.well-known/change-password',
            'phil_well_known_did_configuration_json' => '/.well-known/did-configuration.json',
            'phil_well_known_did_json' => '/.well-known/did.json',
            'phil_well_known_nodeinfo' => '/.well-known/nodeinfo',
            'phil_well_known_security_txt' => '/.well-known/security.txt',
            'phil_well_known_traffic_advice' => '/.well-known/traffic-advice',
            'phil_well_known_webfinger' => '/.well-known/webfinger',
            'phil_well_known_nodeinfo_document' => '/node-info',
        ], $container->getParameter('phil_well_known.routes'));
        self::assertSame($container->getParameter('phil_well_known.routes'), $container->getDefinition(WellKnownRouteLoader::class)->getArgument(0));
        $reference = $container->getDefinition(WellKnownController::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $reference);
        $locator = $container->getDefinition((string) $reference);
        $services = $locator->getArgument(0);
        self::assertIsArray($services);
        self::assertEquals(new ServiceClosureArgument(new Reference('phil_well_known.document.security.txt')), $services['phil_well_known_security_txt']);
        self::assertCount(12, $services);
    }

    public function testInlineDocumentsAndRoutes(): void
    {
        $container = $this->load([
            'change_password' => ['route' => 'password', 'parameters' => ['tab-name' => 'security']],
            'nodeinfo' => ['provider' => 'app.nodeinfo'],
            'did' => ['document' => ['@context' => 'https://www.w3.org/ns/did/v1', 'id' => 'did:web:example.com']],
            'atproto' => ['did' => 'did:plc:site'],
            'apple_app_site_association' => ['document' => ['applinks' => ['details' => []]]],
            'asset_links' => ['android_apps' => [['package_name' => 'a_1.B2', 'sha256_cert_fingerprints' => [self::FINGERPRINT]], ['package_name' => 'c.d', 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]],
            'did_configuration' => ['linked_dids' => [['type' => 'DomainLinkageCredential']]],
        ]);

        self::assertEquals([ChangePasswordDocument::class, ['password', new Reference('router'), ['tab-name' => 'security']]], $this->document($container, 'change-password'));
        self::assertEquals(new Reference('app.nodeinfo'), $container->getDefinition('phil_well_known.document./nodeinfo/2.1')->getArgument(0));
        self::assertSame(['activitypub'], $container->getDefinition('phil_well_known.document./nodeinfo/2.1')->getArgument(1));
        self::assertFalse($container->hasDefinition(ConfiguredNodeInfoProvider::class));
        self::assertSame([StaticDocument::class, ['{"@context":"https://www.w3.org/ns/did/v1","id":"did:web:example.com"}', 'application/did+ld+json', true]], $this->document($container, 'did.json'));
        self::assertSame([AtprotoDidDocument::class, ['did:plc:site', []]], $this->document($container, 'atproto-did'));
        self::assertSame('{"applinks":{"details":[]}}', $container->getDefinition('phil_well_known.document.apple-app-site-association')->getArgument(0));
        self::assertSame('[{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"a_1.B2","sha256_cert_fingerprints":["'.self::FINGERPRINT.'"]}},{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"c.d","sha256_cert_fingerprints":["'.self::FINGERPRINT.'"]}}]', $container->getDefinition('phil_well_known.document.assetlinks.json')->getArgument(0));
        self::assertSame('{"@context":"https://identity.foundation/.well-known/did-configuration/v1","linked_dids":[{"type":"DomainLinkageCredential"}]}', $container->getDefinition('phil_well_known.document.did-configuration.json')->getArgument(0));
        self::assertNull($container->getDefinition(WellKnownController::class)->getArgument(0));
        self::assertFalse($container->hasDefinition('phil_well_known.document.security.txt'));
        self::assertFalse($container->hasDefinition(WebFinger::class));

        $default = $this->load(['nodeinfo' => true]);
        self::assertEquals([new Definition(NodeInfoSoftware::class, ['phil', '0.1.0', null, null]), false, []], $default->getDefinition(ConfiguredNodeInfoProvider::class)->getArguments());
        self::assertEquals([NodeInfoDocument::class, [new Reference(ConfiguredNodeInfoProvider::class), ['activitypub'], [], []]], $this->document($default, '/nodeinfo/2.1'));
        self::assertSame([SecurityTxtDocument::class, ["Contact: tel:+15550123\nExpires: 2099-01-01T00:00:00Z\n", null]], $this->document($this->load(['security_txt' => ['contact' => ['tel:+15550123'], 'expires' => '2099-01-01']]), 'security.txt'));
        self::assertSame([], $this->load(['webfinger' => true])->getDefinition(StaticWebFingerResolver::class)->getArgument(0));
        self::assertSame('https://accounts.example.com/', $this->load(['change_password' => ['url' => 'https://accounts.example.com/']])->getDefinition('phil_well_known.document.change-password')->getArgument(0));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'a negative cache age' => [['cache_max_age' => -1], 'cache_max_age'];
        yield 'a security.txt without expiry' => [['security_txt' => ['contact' => ['mailto:a@b']]], 'security_txt needs its expires date.'];
        yield 'a security.txt without contact' => [['security_txt' => ['expires' => '2099-01-01']], 'at least one Contact'];
        yield 'an expired security.txt' => [['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2000-01-01']], 'The security.txt expired on 2000-01-01: give it a date in the future.'];
        yield 'a security.txt with a bad contact' => [['security_txt' => ['contact' => ['a@b'], 'expires' => '2099-01-01']], 'Invalid configuration for path "phil_well_known.security_txt": Contact takes'];
        yield 'a warning of no day' => [['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01', 'expiry_warning_days' => 0]], 'expiry_warning_days'];
        yield 'a warning of more than a year' => [['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01', 'expiry_warning_days' => 366]], 'expiry_warning_days'];
        yield 'a security.txt with a bad language' => [['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01', 'preferred_languages' => ['en_US']]], 'Preferred-Languages'];
        yield 'a change-password with neither' => [['change_password' => true], 'change_password takes a route or a URL, one of them.'];
        yield 'a change-password with both' => [['change_password' => ['route' => 'a', 'url' => '/a']], 'one of them'];
        yield 'a change-password to http' => [['change_password' => ['url' => 'http://a']], 'The URL of change_password is https://, or a path starting with "/": "http://a".'];
        yield 'a change-password to another host' => [['change_password' => ['url' => '//evil.example']], 'The URL of change_password'];
        yield 'a change-password to a backslash' => [['change_password' => ['url' => '/\\evil.example']], 'The URL of change_password'];
        yield 'a change-password to a relative path' => [['change_password' => ['url' => 'account']], 'The URL of change_password'];
        yield 'a bad WebFinger resource' => [['webfinger' => ['resources' => ['alice' => []]]], 'absolute URI'];
        yield 'a WebFinger link without relation' => [['webfinger' => ['resources' => ['acct:a@b' => ['links' => [['href' => 'https://b']]]]]], 'rel'];
        yield 'a relative NodeInfo path' => [['nodeinfo' => ['path' => 'nodeinfo/2.1']], 'The path of the NodeInfo document is absolute: "nodeinfo/2.1".'];
        yield 'a bad NodeInfo path' => [['nodeinfo' => ['path' => '/a b']], 'not a well-known path'];
        yield 'a bad NodeInfo software' => [['nodeinfo' => ['software' => ['name' => 'My Site']]], 'software name'];
        yield 'an unknown NodeInfo protocol' => [['nodeinfo' => ['protocols' => ['gopher']]], 'NodeInfo 2.1 protocol'];
        yield 'an unknown inbound service' => [['nodeinfo' => ['services' => ['inbound' => ['smtp']]]], 'inbound service'];
        yield 'an unknown outbound service' => [['nodeinfo' => ['services' => ['outbound' => ['imap']]]], 'outbound service'];
        yield 'no NodeInfo protocol' => [['nodeinfo' => ['protocols' => []]], 'NodeInfo names at least one protocol, and its metadata is a map.'];
        yield 'NodeInfo metadata as a list' => [['nodeinfo' => ['metadata' => ['a']]], 'its metadata is a map'];
        yield 'NodeInfo metadata as a string' => [['nodeinfo' => ['metadata' => 'a']], 'its metadata is a map'];
        yield 'a did with neither' => [['did' => true], 'did takes a document or a file, one of them.'];
        yield 'a did with both' => [['did' => ['document' => ['id' => 'did:web:a'], 'file' => 'x']], 'one of them'];
        yield 'a did document without id' => [['did' => ['document' => ['a' => 'b']]], 'The DID document of did has an "id".'];
        yield 'a did document of another method' => [['did' => ['document' => ['id' => 'did:plc:a']]], 'is not a did:web identifier'];
        yield 'a did document as a list' => [['did' => ['document' => ['a']]], 'The document of did is a map.'];
        yield 'an empty did document' => [['did' => ['document' => []]], 'The document of did is a map.'];
        yield 'a did document as a string' => [['did' => ['document' => 'a']], 'The document of did is a map.'];
        yield 'an apple document as a list' => [['apple_app_site_association' => ['document' => ['a']]], 'The document of apple_app_site_association is a map.'];
        yield 'no linked DID' => [['did_configuration' => true], 'did_configuration links at least one DID.'];
        yield 'a linked DID that is no JWT' => [['did_configuration' => ['linked_dids' => ['a.b']]], 'A linked DID of linked_dids is a JWT, or a JSON-LD credential as a map.'];
        yield 'a linked DID as a list' => [['did_configuration' => ['linked_dids' => [['a']]]], 'A linked DID of linked_dids'];
        yield 'an empty linked DID' => [['did_configuration' => ['linked_dids' => [[]]]], 'A linked DID of linked_dids'];
        yield 'a linked DID with a new line' => [['did_configuration' => ['linked_dids' => ["a.b.c\n"]]], 'A linked DID of linked_dids'];
        yield 'an atproto without DID' => [['atproto' => true], 'atproto names a DID, or the DID of a host.'];
        yield 'an atproto with a bad DID' => [['atproto' => ['did' => 'alice']], '"alice" is not a DID'];
        yield 'an atproto with a bad host DID' => [['atproto' => ['hosts' => ['a.b' => 'alice']]], '"alice" is not a DID'];
        yield 'an atproto with a bad DID while off' => [['atproto' => ['enabled' => false, 'did' => 'alice']], '"alice" is not a DID'];
        yield 'no app link' => [['asset_links' => true], 'asset_links names at least one app or statement.'];
        yield 'a package name after a word' => [['asset_links' => ['android_apps' => [['package_name' => 'x com.example.app', 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]]], 'is not an Android package name'];
        yield 'a package name before a word' => [['asset_links' => ['android_apps' => [['package_name' => 'com.example.app x', 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]]], 'is not an Android package name'];
        yield 'a package name and a new line' => [['asset_links' => ['android_apps' => [['package_name' => "com.example.app\n", 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]]], 'is not an Android package name'];
        yield 'a fingerprint after a byte' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => ['00:'.self::FINGERPRINT]]]]], 'is not a SHA-256 fingerprint'];
        yield 'a fingerprint before a byte' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => [self::FINGERPRINT.':00']]]]], 'is not a SHA-256 fingerprint'];
        yield 'a fingerprint and a new line' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => [self::FINGERPRINT."\n"]]]]], 'is not a SHA-256 fingerprint'];
        yield 'a bad package name' => [['asset_links' => ['android_apps' => [['package_name' => 'app', 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]]], '"app" is not an Android package name.'];
        yield 'a package name that is no string' => [['asset_links' => ['android_apps' => [['package_name' => 1, 'sha256_cert_fingerprints' => [self::FINGERPRINT]]]]], 'is not an Android package name'];
        yield 'no fingerprint' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => []]]]], 'sha256_cert_fingerprints'];
        yield 'a lowercase fingerprint' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => [strtolower(self::FINGERPRINT)]]]]], 'is not a SHA-256 fingerprint'];
        yield 'a short fingerprint' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => ['AB:CD']]]]], 'is not a SHA-256 fingerprint'];
        yield 'a fingerprint that is no string' => [['asset_links' => ['android_apps' => [['package_name' => 'a.b', 'sha256_cert_fingerprints' => [true]]]]], 'is not a SHA-256 fingerprint'];
        yield 'a document with neither' => [['documents' => ['a' => ['content_type' => 'text/plain']]], 'A document takes a content or a file, one of them.'];
        yield 'a document with both' => [['documents' => ['a' => ['content' => 'a', 'file' => 'b', 'content_type' => 'text/plain']]], 'one of them'];
        yield 'a document without type' => [['documents' => ['a' => ['content' => 'a']]], 'content_type'];
        yield 'a document at a bad path' => [['documents' => ['../a' => ['content' => 'a', 'content_type' => 'text/plain']]], '"../a" is not a well-known path'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheExpiryOfTheSecurityTxtIsAHealthCheck(): void
    {
        $definition = $this->load(['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01T02:00:00+02:00', 'expiry_warning_days' => 365]])->getDefinition(SecurityTxtExpiryCheck::class);

        self::assertEquals([new Definition(DateTimeImmutable::class, ['2099-01-01T02:00:00+02:00']), new Reference('clock'), 365], $definition->getArguments());
        self::assertTrue($definition->hasTag(PhilMonitoringBundle::HEALTH_CHECK_TAG));
        self::assertSame(1, $this->load(['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01', 'expiry_warning_days' => 1]])->getDefinition(SecurityTxtExpiryCheck::class)->getArgument(2));
        self::assertSame(30, $this->load(['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => '2099-01-01']])->getDefinition(SecurityTxtExpiryCheck::class)->getArgument(2));
        self::assertFalse($this->load(['security_txt' => ['enabled' => false]])->hasDefinition(SecurityTxtExpiryCheck::class));
    }

    public function testASecurityTxtThatExpiresNowIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The security.txt expired on');

        $this->load(['security_txt' => ['contact' => ['mailto:a@b'], 'expires' => gmdate('Y-m-d\TH:i:s\Z')]]);
    }

    public function testAValueOfADocumentTurnedOffIsAccepted(): void
    {
        $container = $this->load(['change_password' => ['enabled' => false], 'did' => ['enabled' => false], 'did_configuration' => ['enabled' => false], 'asset_links' => ['enabled' => false], 'security_txt' => ['enabled' => false, 'expires' => '2000-01-01']]);

        self::assertCount(3, $container->getDefinitions());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badFiles(): iterable
    {
        yield 'a missing did file' => [['did' => ['file' => '%dir%/missing.json']], 'The well-known document file "%dir%/missing.json" cannot be read.'];
        yield 'a did file that is no JSON' => [['did' => ['file' => '%dir%/bad.json']], 'The well-known document file "%dir%/bad.json" is not JSON: Syntax error'];
        yield 'a did file without id' => [['did' => ['file' => '%dir%/list.json']], 'The well-known document file "%dir%/list.json": The document of did is a map.'];
        yield 'an apple file as a list' => [['apple_app_site_association' => ['file' => '%dir%/list.json']], 'The well-known document file "%dir%/list.json": The document of apple_app_site_association is a map.'];
        yield 'a missing credential' => [['did_configuration' => ['files' => ['%dir%/missing.jwt']]], 'cannot be read'];
        yield 'a credential that is no JWT' => [['did_configuration' => ['files' => ['%dir%/list.json']]], 'The well-known document file "%dir%/list.json": A linked DID of %dir%/list.json is a JWT'];
        yield 'a credential that is bad JSON' => [['did_configuration' => ['files' => ['%dir%/bad.json']]], 'is not JSON'];
        yield 'a missing document' => [['documents' => ['a' => ['file' => '%dir%/missing.txt', 'content_type' => 'text/plain']]], 'cannot be read'];
        yield 'a directory' => [['documents' => ['a' => ['file' => '%dir%', 'content_type' => 'text/plain']]], 'cannot be read'];
        yield 'a deep did file' => [['did' => ['file' => '%dir%/deep.json']], 'Maximum stack depth exceeded'];
        yield 'a did file just deep enough' => [['did' => ['file' => '%dir%/deepest.json']], 'The document of did is a map.'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badFiles')]
    public function testABadFileIsRefused(array $config, string $message): void
    {
        file_put_contents($this->directory.'/bad.json', '{');
        file_put_contents($this->directory.'/list.json', '["a"]');
        file_put_contents($this->directory.'/deep.json', str_repeat('[', 64).str_repeat(']', 64));
        file_put_contents($this->directory.'/deepest.json', str_repeat('[', 63).str_repeat(']', 63));
        $replace = fn (mixed $value): mixed => \is_string($value) ? str_replace('%dir%', $this->directory, $value) : $value;
        array_walk_recursive($config, static function (mixed &$value) use ($replace): void {
            $value = $replace($value);
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(str_replace('%dir%', $this->directory, $message));

        $this->load($config);
    }

    public function testAFileNamedByAParameter(): void
    {
        file_put_contents($this->directory.'/body.txt', 'body');
        $container = new ContainerBuilder();
        $container->setParameter('app.dir', $this->directory);
        $this->extension()->load([['documents' => ['a' => ['file' => '%app.dir%/body.txt', 'content_type' => 'text/plain']]]], $container);

        self::assertSame('body', $container->getDefinition('phil_well_known.document.a')->getArgument(0));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilWellKnownBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof WellKnownPass));
    }

    public function testThePassSkipsAContainerWithoutTheBundle(): void
    {
        $container = new ContainerBuilder();
        $container->register('doc', StaticDocument::class)->addTag('phil_well_known.document', ['path' => 'a']);

        new WellKnownPass()->process($container);

        self::assertFalse($container->hasParameter('phil_well_known.routes'));
    }

    public function testADocumentOfAnotherBundle(): void
    {
        $container = $this->load([]);
        $container->setParameter('doc.class', StaticDocument::class);
        $container->register('doc', '%doc.class%')->addTag('phil_well_known.document', ['path' => 'a'])->addTag('phil_well_known.document', ['path' => '/b', 'route' => 'b_route']);

        new WellKnownPass()->process($container);

        self::assertSame(['phil_well_known_a' => '/.well-known/a', 'b_route' => '/b'], $container->getParameter('phil_well_known.routes'));
    }

    /**
     * @return iterable<string, array{list<array{?string, array<string, mixed>}>, string}>
     */
    public static function badTags(): iterable
    {
        yield 'no path' => [[[StaticDocument::class, []]], 'The tag "phil_well_known.document" of the service "doc0" needs a "path", and its "route" is a string.'];
        yield 'a path that is no string' => [[[StaticDocument::class, ['path' => 1]]], 'needs a "path"'];
        yield 'a route that is no string' => [[[StaticDocument::class, ['path' => 'a', 'route' => 1]]], 'needs a "path"'];
        yield 'a bad path' => [[[StaticDocument::class, ['path' => '../a']]], 'The well-known document "doc0": "../a" is not a well-known path'];
        yield 'a class that is no document' => [[[stdClass::class, ['path' => 'a']]], 'The well-known document "doc0" is a "stdClass": it implements "Phil\WellKnownBundle\Application\Contract\WellKnownDocument".'];
        yield 'no class' => [[[null, ['path' => 'a']]], 'The well-known document "doc0" is a "null"'];
        yield 'a path twice' => [[[StaticDocument::class, ['path' => 'a']], [StaticDocument::class, ['path' => 'a', 'route' => 'other']]], 'The well-known document "doc1" is served at "/.well-known/a", by the route "other": another document already is.'];
        yield 'a route twice' => [[[StaticDocument::class, ['path' => 'a', 'route' => 'r']], [StaticDocument::class, ['path' => 'b', 'route' => 'r']]], 'another document already is'];
    }

    /**
     * @param list<array{?string, array<string, mixed>}> $documents
     */
    #[DataProvider('badTags')]
    public function testABadTagIsRefused(array $documents, string $message): void
    {
        $container = $this->load([]);

        foreach ($documents as $index => [$class, $tag]) {
            $container->register('doc'.$index, $class)->addTag('phil_well_known.document', $tag);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new WellKnownPass()->process($container);
    }

    /**
     * @return array{?string, array<array-key, mixed>}
     */
    private function document(ContainerBuilder $container, string $path): array
    {
        $definition = $container->getDefinition('phil_well_known.document.'.$path);

        if (!str_starts_with($path, '/') && 'nodeinfo' !== $path) {
            self::assertSame([['path' => $path]], $definition->getTag('phil_well_known.document'));
        }

        return [$definition->getClass(), $definition->getArguments()];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilWellKnownBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
