<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Metadata;

use DateTimeImmutable;
use DOMDocument;
use InvalidArgumentException;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Metadata\IdentityProviderMetadata;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Metadata\ServiceProviderMetadata;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Tests\Support\CountingFetcher;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Tests\Support\TestSigner;
use Phil\SamlSp\Xml\SafeXml;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use ValueError;

#[CoversClass(MetadataReader::class)]
#[CoversClass(MetadataLoader::class)]
#[CoversClass(IdentityProviderMetadata::class)]
#[CoversClass(ServiceProviderMetadata::class)]
final class MetadataTest extends TestCase
{
    public function testTheMetadataOfMicrosoftEntraIdIsRead(): void
    {
        $metadata = new MetadataReader()->read($this->entra(), trusted: [Keys::certificate('entra')]);

        self::assertSame(Stage::ENTRA, $metadata->entityId);
        self::assertSame(['https://login.microsoftonline.com/t/saml2', 'https://login.microsoftonline.com/t/saml2'], [$metadata->singleSignOnServices[Binding::Redirect->value]->toString(), $metadata->singleSignOnServices[Binding::Post->value]->toString()]);
        self::assertSame('https://login.microsoftonline.com/t/saml2/logout', $metadata->singleLogoutServices[Binding::Redirect->value]->toString());
        self::assertCount(2, $metadata->signingCertificates);
        self::assertTrue($metadata->signingCertificates[0]->equals(Keys::certificate('entra')));
        self::assertTrue($metadata->signingCertificates[1]->equals(Keys::certificate('okta')));
        self::assertFalse($metadata->wantAuthnRequestsSigned);
        self::assertSame('2126-01-01T00:00:00+00:00', $metadata->validUntil?->format('c'));
    }

    public function testUnsignedMetadataIsReadWhenNoCertificateIsTrusted(): void
    {
        $metadata = new MetadataReader()->read(self::okta());

        self::assertSame(Stage::OKTA, $metadata->entityId);
        self::assertTrue($metadata->wantAuthnRequestsSigned);
        self::assertNull($metadata->validUntil);
        self::assertSame([Binding::Post->value], array_keys($metadata->singleSignOnServices));
        self::assertSame([], $metadata->singleLogoutServices);
        self::assertCount(1, $metadata->signingCertificates);
    }

    public function testUnsignedMetadataIsRefusedWhenCertificatesAreTrusted(): void
    {
        $this->expectException(InvalidMetadata::class);
        $this->expectExceptionMessageIsOrContains('The metadata is refused: The element carries no signature, or more than one.');

        new MetadataReader()->read(self::okta(), trusted: [Keys::certificate('okta')]);
    }

    public function testMetadataSignedByAnotherKeyIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The metadata is refused: The signature does not verify with the certificates of the identity provider.');

        new MetadataReader()->read($this->entra(), trusted: [Keys::certificate('okta')]);
    }

    public function testAFederationIsReadByEntityId(): void
    {
        $federation = '<md:EntitiesDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata">'.$this->strip(self::okta()).$this->strip(self::okta('https://other.example.org')).'</md:EntitiesDescriptor>';

        self::assertSame('https://other.example.org', new MetadataReader()->read($federation, 'https://other.example.org')->entityId);
        self::assertSame(Stage::OKTA, new MetadataReader()->read('<md:EntitiesDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata">'.$this->strip(self::okta()).'</md:EntitiesDescriptor>')->entityId);

        try {
            new MetadataReader()->read($federation);
            self::fail('A federation was read without an entity ID.');
        } catch (InvalidMetadata $invalidMetadata) {
            self::assertSame('The metadata describes several entities: name the one to read.', $invalidMetadata->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The metadata does not describe "https://missing.example.org".');
        new MetadataReader()->read($federation, 'https://missing.example.org');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'not metadata' => ['<a/>', 'The metadata is not an EntityDescriptor nor an EntitiesDescriptor.'];
        yield 'not XML' => ['<a', 'The metadata is refused: The document is not well-formed XML.'];
        yield 'no identity provider' => [str_replace('IDPSSODescriptor', 'SPSSODescriptor', self::okta()), 'The metadata has no SAML 2.0 identity provider.'];
        yield 'SAML 1.1 only' => [str_replace('urn:oasis:names:tc:SAML:2.0:protocol', 'urn:oasis:names:tc:SAML:1.1:protocol', self::okta()), 'The metadata has no SAML 2.0 identity provider.'];
        yield 'no certificate' => [str_replace('use="signing"', 'use="encryption"', self::okta()), 'An identity provider has an entity ID, a sign-on service on HTTP-Redirect or HTTP-POST and a signing certificate.'];
        yield 'bad certificate' => [preg_replace('{<ds:X509Certificate>[^<]+}', '<ds:X509Certificate>AAAA', self::okta()) ?? '', 'The certificate is not an X.509 certificate in PEM.'];
        yield 'no sign-on service' => [str_replace('HTTP-POST', 'SOAP', self::okta()), 'An identity provider has an entity ID'];
        yield 'plain HTTP service' => [str_replace('https://example.okta.com', 'http://example.okta.com', self::okta()), 'The service address "http://example.okta.com/app/sso" is not HTTPS.'];
        yield 'bad address' => [str_replace('https://example.okta.com/app/sso', 'javascript:alert(1)', self::okta()), 'The service address "javascript:alert(1)" is refused.'];
        yield 'localhost' => [str_replace('https://example.okta.com/app/sso', 'https://localhost/sso', self::okta()), 'The service address "https://localhost/sso" is refused.'];
        yield 'another entity' => [self::okta(), 'The metadata describes another entity than "https://other.example.org".'];
        yield 'bad validity' => [str_replace('entityID=', 'validUntil="tomorrow" entityID=', self::okta()), 'The validUntil of the metadata is not an instant in UTC.'];
        yield 'no entity ID' => [str_replace('entityID="'.Stage::OKTA.'"', '', self::okta()), 'An identity provider has an entity ID'];
    }

    #[DataProvider('refused')]
    public function testRefusedMetadata(string $xml, string $message): void
    {
        $this->expectException(InvalidMetadata::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MetadataReader()->read($xml, str_contains($message, 'another entity') ? 'https://other.example.org' : null);
    }

    public function testLocalhostIsReadWhenAllowed(): void
    {
        $metadata = new MetadataReader(allowLocalhost: true)->read(str_replace('https://example.okta.com/app/sso', 'http://localhost:8080/sso', self::okta()));

        self::assertSame('http://localhost:8080/sso', $metadata->singleSignOnServices[Binding::Post->value]->toString());
    }

    public function testTheBudgetOfTheReaderBoundsTheMetadata(): void
    {
        $this->expectExceptionMessageIsOrContains('The metadata is refused: A document has 1 to 100 bytes.');

        new MetadataReader(budget: new XmlBudget(100))->read(self::okta());
    }

    public function testThePreferredBindingIsPicked(): void
    {
        $metadata = new MetadataReader()->read($this->entra());

        self::assertSame(Binding::Post, $metadata->singleSignOn(Binding::Post)[0]);
        self::assertSame(Binding::Redirect, $metadata->singleSignOn(Binding::Redirect)[0]);
        self::assertSame(Binding::Redirect, $metadata->singleLogout(Binding::Post)[0] ?? null);

        $okta = new MetadataReader()->read(self::okta());
        self::assertSame(Binding::Post, $okta->singleSignOn(Binding::Redirect)[0]);
        self::assertNull($okta->singleLogout(Binding::Redirect));
    }

    #[DoesNotPerformAssertions]
    public function testAnIncompleteIdentityProviderIsRefused(): void
    {
        foreach ([[' ', [Binding::Post->value => Url::fromString('https://a.example.org')], [Keys::certificate('okta')]], ['x', [], [Keys::certificate('okta')]], ['x', [Binding::Post->value => Url::fromString('https://a.example.org')], []]] as [$entity, $services, $certificates]) {
            try {
                new IdentityProviderMetadata($entity, $services, [], $certificates);
                self::fail('An incomplete identity provider was accepted.');
            } catch (InvalidMetadata) {
            }
        }
    }

    public function testTheDefaultsOfAnIdentityProvider(): void
    {
        $metadata = new IdentityProviderMetadata('x', [Binding::Post->value => Url::fromString('https://a.example.org')], [], [Keys::certificate('okta')]);

        self::assertFalse($metadata->wantAuthnRequestsSigned);
        self::assertNull($metadata->validUntil);
    }

    public function testTheBindingsOfTheServicesAreKnown(): void
    {
        $url = Url::fromString('https://a.example.org');

        $refused = 0;

        foreach ([[['nope' => $url], []], [[Binding::Post->value => $url], ['nope' => $url]]] as [$sso, $slo]) {
            try {
                new ReflectionClass(IdentityProviderMetadata::class)->newInstanceArgs(['x', $sso, $slo, [Keys::certificate('okta')]]);
            } catch (ValueError) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testTheLogoutServiceOnThePreferredBindingIsPicked(): void
    {
        $redirect = Url::fromString('https://a.example.org/redirect');
        $post = Url::fromString('https://a.example.org/post');
        $metadata = new IdentityProviderMetadata('x', [Binding::Post->value => $post], [Binding::Redirect->value => $redirect, Binding::Post->value => $post], [Keys::certificate('okta')]);

        self::assertSame([Binding::Post, $post], $metadata->singleLogout(Binding::Post));
        self::assertSame([Binding::Redirect, $redirect], $metadata->singleLogout(Binding::Redirect));
    }

    public function testTheReaderIsBoundedByDefault(): void
    {
        $reader = new MetadataReader();
        $refuse = static function (string $xml) use ($reader): string {
            try {
                $reader->read($xml);
            } catch (InvalidMetadata $invalidMetadata) {
                return $invalidMetadata->getMessage();
            }

            return 'accepted';
        };
        $notMetadata = 'The metadata is refused: ';
        $bytes = static fn (int $size): string => '<a>'.str_repeat('x', $size - 7).'</a>';
        $nodes = static fn (int $count): string => '<a>'.str_repeat('<b/>', $count - 1).'</a>';
        $depth = static fn (int $levels): string => str_repeat('<a>', $levels).str_repeat('</a>', $levels);

        self::assertSame('The metadata is not an EntityDescriptor nor an EntitiesDescriptor.', $refuse($bytes(4 * 1024 * 1024)));
        self::assertSame($notMetadata.'A document has 1 to 4194304 bytes.', $refuse($bytes((4 * 1024 * 1024) + 1)));
        self::assertSame('The metadata is not an EntityDescriptor nor an EntitiesDescriptor.', $refuse($nodes(200_000)));
        self::assertSame($notMetadata.'A document has 200000 nodes at most.', $refuse($nodes(200_001)));
        self::assertSame('The metadata is not an EntityDescriptor nor an EntitiesDescriptor.', $refuse($depth(48)));
        self::assertSame($notMetadata.'A document is 48 levels deep at most.', $refuse($depth(49)));
    }

    public function testARefusalOfTheDescriptionIsNotWrapped(): void
    {
        try {
            new MetadataReader()->read(str_replace('HTTP-POST', 'SOAP', self::okta()));
            self::fail('The metadata was read.');
        } catch (InvalidMetadata $invalidMetadata) {
            self::assertNull($invalidMetadata->getPrevious());
        }
    }

    public function testAnEncryptionKeyBeforeTheSigningKeyIsSkipped(): void
    {
        $xml = str_replace(self::key('okta', 'signing'), self::key('sp', 'encryption').self::key('okta', 'signing'), self::okta());
        $certificates = new MetadataReader()->read($xml)->signingCertificates;

        self::assertCount(1, $certificates);
        self::assertTrue(Keys::certificate('okta')->equals($certificates[0]));
    }

    public function testTheMetadataOfTheServiceProviderWantsSignedAssertionsByDefault(): void
    {
        self::assertStringContainsString('WantAssertionsSigned="true"', ServiceProviderMetadata::xml(Stage::sp()));
    }

    public function testTheMetadataIsCachedForADayByDefault(): void
    {
        $cache = new ArrayAdapter();
        new MetadataLoader(new CountingFetcher(self::okta()), Stage::clock(), $cache)->fromUrl(Url::fromString('https://example.okta.com/app/metadata'));
        $expiries = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($cache);
        self::assertIsArray($expiries);

        self::assertEqualsWithDelta(microtime(true) + 86_400, array_first($expiries), 0.9);
    }

    public function testTheMetadataOfTheServiceProvider(): void
    {
        $xml = ServiceProviderMetadata::xml(Stage::sp(), true, new DateTimeImmutable('2027-01-01 00:00:00 UTC'));
        $document = SafeXml::load($xml, XmlBudget::metadata());

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertSame(Stage::SP, $document->documentElement?->getAttribute('entityID'));
        self::assertSame('2027-01-01T00:00:00Z', $document->documentElement->getAttribute('validUntil'));
        self::assertStringContainsString('<md:SPSSODescriptor AuthnRequestsSigned="true" WantAssertionsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">', $xml);
        self::assertStringContainsString('<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.Keys::certificate('sp')->base64().'</ds:X509Certificate>', $xml);
        self::assertStringContainsString('<md:KeyDescriptor use="encryption">', $xml);
        self::assertStringContainsString('<md:EncryptionMethod Algorithm="http://www.w3.org/2009/xmlenc11#aes256-gcm"/><md:EncryptionMethod Algorithm="http://www.w3.org/2009/xmlenc11#aes128-gcm"/><md:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/><md:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/>', $xml);
        self::assertStringContainsString('<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="'.Stage::SLO.'"/><md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="'.Stage::SLO.'"/>', $xml);
        self::assertStringContainsString('<md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="'.Stage::ACS.'" index="0" isDefault="true"/>', $xml);
        self::assertStringNotContainsString('NameIDFormat', $xml);
    }

    public function testTheMetadataOfAServiceProviderWithoutKeys(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), nameIdFormat: 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', signRequests: false);
        $xml = ServiceProviderMetadata::xml($sp, false);

        self::assertStringContainsString('AuthnRequestsSigned="false" WantAssertionsSigned="false"', $xml);
        self::assertStringContainsString('<md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</md:NameIDFormat>', $xml);
        self::assertStringNotContainsString('KeyDescriptor', $xml);
        self::assertStringNotContainsString('SingleLogoutService', $xml);
        self::assertStringNotContainsString('validUntil', $xml);
    }

    public function testTheLoaderReadsXmlAndRefusesExpiredMetadata(): void
    {
        $loader = new MetadataLoader(new CountingFetcher(''), Stage::clock());

        self::assertSame(Stage::OKTA, $loader->fromXml(self::okta())->entityId);

        $this->expectExceptionMessageIsOrContains('The metadata of "'.Stage::OKTA.'" expired.');
        $loader->fromXml(str_replace('entityID=', 'validUntil="2026-11-19T12:01:00Z" entityID=', self::okta()));
    }

    public function testTheLoaderAcceptsMetadataValidALittleLonger(): void
    {
        self::assertNotNull(new MetadataLoader(new CountingFetcher(''), Stage::clock())->fromXml(str_replace('entityID=', 'validUntil="2026-11-19T12:01:01Z" entityID=', self::okta()))->validUntil);
    }

    public function testTheLoaderCachesWhatItFetches(): void
    {
        $fetcher = new CountingFetcher(self::okta());
        $cache = new ArrayAdapter();
        $loader = new MetadataLoader($fetcher, Stage::clock(), $cache, 3600);
        $url = Url::fromString('https://example.okta.com/app/metadata');

        self::assertSame(Stage::OKTA, $loader->fromUrl($url)->entityId);
        self::assertSame(Stage::OKTA, $loader->fromUrl($url)->entityId);
        self::assertSame(1, $fetcher->calls);
        self::assertTrue($cache->hasItem('phil_saml_sp.metadata.'.hash('sha256', $url->toString())));

        $loader->fromUrl($url, refresh: true);
        self::assertSame(2, $fetcher->calls);
    }

    public function testTheLoaderForgetsMetadataItRefuses(): void
    {
        $fetcher = new CountingFetcher('<a/>');
        $cache = new ArrayAdapter();
        $loader = new MetadataLoader($fetcher, Stage::clock(), $cache);
        $url = Url::fromString('https://example.okta.com/app/metadata');

        try {
            $loader->fromUrl($url);
            self::fail('The metadata was read.');
        } catch (InvalidMetadata) {
        }

        self::assertFalse($cache->hasItem('phil_saml_sp.metadata.'.hash('sha256', $url->toString())));
    }

    public function testTheLoaderWithoutCacheFetchesEachTime(): void
    {
        $fetcher = new CountingFetcher(self::okta());
        $loader = new MetadataLoader($fetcher, Stage::clock());
        $url = Url::fromString('https://example.okta.com/app/metadata');
        $loader->fromUrl($url);
        $loader->fromUrl($url, Stage::OKTA);

        self::assertSame(2, $fetcher->calls);
    }

    public function testTheCacheLifetimeIsBounded(): void
    {
        self::assertNotNull(new MetadataLoader(new CountingFetcher(''), Stage::clock(), null, 60));
        self::assertNotNull(new MetadataLoader(new CountingFetcher(''), Stage::clock(), null, 2_592_000));

        foreach ([59, 2_592_001] as $lifetime) {
            try {
                new MetadataLoader(new CountingFetcher(''), Stage::clock(), null, $lifetime);
                self::fail('The lifetime was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(\sprintf('Metadata is cached 60 seconds to 30 days; %d seconds is not.', $lifetime), $exception->getMessage());
            }
        }
    }

    public function testTheCacheItemExpiresAfterTheLifetime(): void
    {
        $cache = new ArrayAdapter();
        $clock = Stage::clock();
        $loader = new MetadataLoader(new CountingFetcher(self::okta()), $clock, $cache, 60);
        $loader->fromUrl(Url::fromString('https://example.okta.com/app/metadata'));

        $values = $cache->getValues();

        self::assertCount(1, $values);
        $expiries = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($cache);
        self::assertIsArray($expiries);
        self::assertEqualsWithDelta(microtime(true) + 60, array_first($expiries), 5);
    }

    private function entra(): string
    {
        $xml = '<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" ID="_metadata" entityID="'.Stage::ENTRA.'" validUntil="2126-01-01T00:00:00Z">'
            .'<md:RoleDescriptor xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:fed="http://docs.oasis-open.org/wsfed/federation/200706" xsi:type="fed:SecurityTokenServiceType" protocolSupportEnumeration="http://docs.oasis-open.org/wsfed/federation/200706"/>'
            .'<md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            .self::key('entra', 'signing').self::key('okta', '').self::key('entra', '').self::key('sp', 'encryption')
            .'<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://login.microsoftonline.com/t/saml2/logout"/>'
            .'<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://login.microsoftonline.com/t/saml2"/>'
            .'<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://login.microsoftonline.com/t/second"/>'
            .'<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://login.microsoftonline.com/t/saml2"/>'
            .'<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:SOAP" Location="ftp://ignored"/>'
            .'</md:IDPSSODescriptor></md:EntityDescriptor>';
        $document = new DOMDocument();
        $document->loadXML($xml);
        TestSigner::sign($document->documentElement ?? throw new RuntimeException(), 'entra');

        return (string) $document->saveXML();
    }

    private static function okta(string $entity = Stage::OKTA): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="'.$entity.'">'
            .'<md:IDPSSODescriptor WantAuthnRequestsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            .self::key('okta', 'signing')
            .'<md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified</md:NameIDFormat>'
            .'<md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://example.okta.com/app/sso"/>'
            .'</md:IDPSSODescriptor></md:EntityDescriptor>';
    }

    private static function key(string $name, string $use): string
    {
        return '<md:KeyDescriptor'.('' === $use ? '' : ' use="'.$use.'"').'><ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.chunk_split(Keys::certificate($name)->base64(), 64, "\n").'</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>';
    }

    private function strip(string $xml): string
    {
        return (string) preg_replace('/^<\?xml[^>]*\?>/', '', $xml);
    }
}
