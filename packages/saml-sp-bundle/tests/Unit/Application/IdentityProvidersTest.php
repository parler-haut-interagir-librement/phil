<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Signature\Signer;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Phil\SamlSpBundle\Tests\Fixtures\FixedEndpoints;
use Phil\SamlSpBundle\Tests\Fixtures\NoFetcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IdentityProviders::class)]
#[CoversClass(IdentityProviderSettings::class)]
#[CoversClass(ServiceProviderSettings::class)]
final class IdentityProvidersTest extends TestCase
{
    public function testTheIdentityProvidersByKey(): void
    {
        $providers = Saml::providers(new MockClock(), [Saml::settings(), Saml::settings('okta', binding: Binding::Post, allowUnsolicited: true)]);

        self::assertSame(['acme', 'okta'], $providers->keys());
        self::assertTrue($providers->has('okta'));
        self::assertFalse($providers->has('other'));
        self::assertSame('okta', $providers->settings('okta')->key);

        $idp = $providers->identityProvider('okta');
        self::assertSame(Saml::IDP, $idp->metadata->entityId);
        self::assertSame(Binding::Post, $idp->requestBinding);
        self::assertTrue($idp->allowUnsolicited);
        self::assertTrue($idp->wantAssertionsSigned);
        self::assertFalse($idp->wantAssertionsEncrypted);
        self::assertSame(['http://schemas.microsoft.com/identity/claims/objectidentifier'], $idp->attributes->names['identifier']);
        self::assertSame($idp, $providers->identityProvider('okta'));
        self::assertNotSame($idp, $providers->identityProvider('okta', true));

        $providers->reset();
        self::assertNotSame($idp, $providers->identityProvider('okta'));
    }

    public function testAnUnknownIdentityProvider(): void
    {
        $this->expectException(InvalidMetadata::class);
        $this->expectExceptionMessageIsOrContains('No identity provider is configured under "other".');

        Saml::providers(new MockClock())->identityProvider('other');
    }

    public function testTheServiceProviderEachIdentityProviderSees(): void
    {
        $providers = Saml::providers(new MockClock(), [Saml::settings(), Saml::settings('quiet', logout: false)]);
        $sp = $providers->serviceProvider('acme');

        self::assertSame('https://app.example.com/saml/acme/metadata', $sp->entityId);
        self::assertSame('https://app.example.com/saml/acme/acs', $sp->assertionConsumerService->toString());
        self::assertSame('https://app.example.com/saml/acme/slo', $sp->singleLogoutService?->toString());
        self::assertNotNull($sp->signing);
        self::assertCount(1, $sp->encryption);
        self::assertTrue($sp->signRequests);
        self::assertNull($providers->serviceProvider('quiet')->singleLogoutService);
        self::assertSame($providers->signing(), $providers->signing());
        self::assertSame($providers->encryption(), $providers->encryption());

        $providers->reset();
        self::assertNotSame($sp->signing, $providers->signing());
    }

    public function testAServiceProviderOfItsOwn(): void
    {
        $providers = new IdentityProviders([Saml::settings()], new ServiceProviderSettings('https://app.example.com/sp', null, [], 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', false), new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());
        $sp = $providers->serviceProvider('acme');

        self::assertSame('https://app.example.com/sp', $sp->entityId);
        self::assertNull($sp->signing);
        self::assertNull($providers->signing());
        self::assertSame([], $sp->encryption);
        self::assertFalse($sp->signRequests);
        self::assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $sp->nameIdFormat);
        self::assertSame([], new ServiceProviderSettings(null, Saml::pemPair('sp'))->__debugInfo());

        $this->expectException(InvalidMetadata::class);
        $providers->serviceProvider('other');
    }

    public function testMetadataFromAFileOrAnAddress(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'saml');
        self::assertIsString($file);
        file_put_contents($file, Saml::metadata());
        $providers = Saml::providers(new MockClock(), [
            new IdentityProviderSettings('file', null, null, $file),
            new IdentityProviderSettings('missing', null, null, $file.'.missing'),
            new IdentityProviderSettings('remote', 'https://idp.example.org/metadata', null),
        ]);

        self::assertSame(Saml::IDP, $providers->identityProvider('file')->metadata->entityId);
        unlink($file);

        try {
            $providers->identityProvider('missing');
            self::fail('A missing file was read.');
        } catch (InvalidMetadata $invalidMetadata) {
            self::assertSame(\sprintf('The metadata file "%s" cannot be read.', $file.'.missing'), $invalidMetadata->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The tests fetch nothing; "https://idp.example.org/metadata" was asked.');
        $providers->identityProvider('remote');
    }

    public function testSignedMetadataAndAnEntity(): void
    {
        $providers = Saml::providers(new MockClock(), [
            new IdentityProviderSettings('trusted', null, Saml::metadata(), metadataCertificates: [Saml::pem('entra.crt')], entityId: Saml::IDP),
        ]);

        $this->expectException(InvalidMetadata::class);
        $this->expectExceptionMessageIsOrContains('The metadata is refused: The element carries no signature, or more than one.');
        $providers->identityProvider('trusted');
    }

    public function testMetadataSignedByATrustedCertificate(): void
    {
        $document = Saml::load(str_replace('<md:EntityDescriptor ', '<md:EntityDescriptor ID="_metadata" ', Saml::metadata()));
        new Signer(Saml::pair('entra'))->sign($document->documentElement ?? throw new RuntimeException('No root.'));
        $providers = Saml::providers(new MockClock(), [
            new IdentityProviderSettings('trusted', null, (string) $document->saveXML(), metadataCertificates: [Saml::pem('entra.crt')], entityId: Saml::IDP),
        ]);

        self::assertSame(Saml::IDP, $providers->identityProvider('trusted')->metadata->entityId);
    }

    public function testRequestsAreSignedByDefault(): void
    {
        self::assertTrue(new ServiceProviderSettings(null, null)->signRequests);
    }

    public function testTheKeysAreReadOnceEachWhateverTheOrder(): void
    {
        $providers = Saml::providers(new MockClock());
        $encryption = $providers->encryption();

        self::assertCount(1, $encryption);
        self::assertContainsOnlyInstancesOf(KeyPair::class, $encryption);
        self::assertSame($encryption, $providers->encryption());
        self::assertNotNull($providers->signing());
        self::assertSame($providers->signing(), $providers->signing());
    }

    public function testTheMetadataOfAnotherEntityIsRefused(): void
    {
        $providers = Saml::providers(new MockClock(), [new IdentityProviderSettings('other', null, Saml::metadata(), entityId: 'https://other.example.org')]);

        $this->expectExceptionMessageIsOrContains('The metadata describes another entity than "https://other.example.org".');
        $providers->identityProvider('other');
    }

    public function testTheSettingsOfAnIdentityProvider(): void
    {
        $settings = new IdentityProviderSettings('acme', 'https://idp.example.org/metadata', null);

        self::assertSame([], $settings->metadataCertificates);
        self::assertNull($settings->entityId);
        self::assertSame([], $settings->attributes->names);
        self::assertSame([], $settings->groupRoles);
        self::assertSame(['ROLE_USER'], $settings->defaultRoles);
        self::assertTrue($settings->wantAssertionsSigned);
        self::assertFalse($settings->wantAssertionsEncrypted);
        self::assertFalse($settings->allowUnsolicited);
        self::assertSame(Binding::Redirect, $settings->binding);
        self::assertTrue($settings->singleLogout);
        self::assertTrue($settings->createAccounts);
        self::assertFalse($settings->forceAuthn);

        foreach ([[null, null, null], ['https://a.example.org', '<x/>', null], ['https://a.example.org', null, '/f'], [null, '<x/>', '/f']] as [$url, $xml, $file]) {
            try {
                new IdentityProviderSettings('acme', $url, $xml, $file);
                self::fail('The settings were accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('The identity provider "acme" has its metadata from one of an address, a file or XML.', $exception->getMessage());
            }
        }
    }
}
