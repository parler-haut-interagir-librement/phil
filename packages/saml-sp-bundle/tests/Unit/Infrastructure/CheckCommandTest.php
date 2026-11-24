<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Infrastructure;

use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Phil\SamlSpBundle\Infrastructure\Console\CheckCommand;
use Phil\SamlSpBundle\Tests\Fixtures\CountingFetcher;
use Phil\SamlSpBundle\Tests\Fixtures\FixedEndpoints;
use Phil\SamlSpBundle\Tests\Fixtures\NoFetcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CheckCommand::class)]
final class CheckCommandTest extends TestCase
{
    public function testEverythingInOrder(): void
    {
        $tester = $this->tester(Saml::providers(new MockClock('2026-11-19 12:00:00 UTC')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('acme', $display);
        self::assertStringContainsString(Saml::IDP, $display);
        self::assertStringContainsString(Saml::SSO, $display);
        self::assertStringContainsString(Saml::IDP_SLO, $display);
        self::assertStringContainsString('not said', $display);
        self::assertStringContainsString('Signing certificates of the identity provider', $display);
        self::assertStringContainsString('Certificates of the service provider', $display);
        self::assertStringContainsString('/CN=Test Entra ID signing', $display);
        self::assertStringContainsString('2126-01-01', $display);
        self::assertStringContainsString(substr(Saml::pair('entra')->certificate->fingerprint(), 0, 16), $display);
        self::assertStringNotContainsString(substr(Saml::pair('entra')->certificate->fingerprint(), 0, 17), $display);
        self::assertStringContainsString('The identity providers and the certificates are in order.', $display);

        foreach (['Entity ID', 'Sign-on', 'Logout', 'Valid until', 'Subject', 'SHA-256', 'Expires', 'State'] as $label) {
            self::assertStringContainsString($label, $display);
        }
    }

    public function testTheDefaultDelayIsThirtyDays(): void
    {
        $providers = Saml::providers(new MockClock('2125-12-02 00:00:00 UTC'));

        self::assertSame(Command::FAILURE, $this->tester($providers, '2125-12-02 00:00:00 UTC')->execute([]));
        self::assertSame(Command::SUCCESS, $this->tester($providers, '2125-12-01 00:00:00 UTC')->execute([]));
    }

    public function testAZeroOrNegativeDelayLooksNoFurtherThanNow(): void
    {
        $providers = Saml::providers(new MockClock('2125-12-31 12:00:00 UTC'));
        $tester = $this->tester($providers, '2125-12-31 12:00:00 UTC');

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '0']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '-5']));
    }

    public function testACertificateExpiresAtItsLastSecond(): void
    {
        $tester = $this->tester(Saml::providers(new MockClock('2126-01-01 00:00:00 UTC')), '2126-01-01 00:00:00 UTC');

        self::assertSame(Command::FAILURE, $tester->execute(['--days' => '0']));
        self::assertStringContainsString('expired', $tester->getDisplay());
        self::assertStringNotContainsString('expires soon', $tester->getDisplay());
    }

    public function testOneIdentityProviderOnly(): void
    {
        $tester = $this->tester(Saml::providers(new MockClock(), [Saml::settings(), Saml::settings('other')]));

        self::assertSame(Command::SUCCESS, $tester->execute(['idp' => 'other']));
        self::assertStringContainsString('other', $tester->getDisplay());
        self::assertStringNotContainsString('acme', $tester->getDisplay());
    }

    public function testTheMetadataIsFetchedAgain(): void
    {
        $fetcher = new CountingFetcher(Saml::metadata());
        $providers = new IdentityProviders(
            [new IdentityProviderSettings('remote', 'https://idp.example.org/metadata', null)],
            new ServiceProviderSettings(null, null, [], null, false),
            new MetadataLoader($fetcher, new MockClock(), new ArrayAdapter()),
            new FixedEndpoints(),
        );
        $providers->identityProvider('remote');

        self::assertSame(1, $fetcher->calls);
        self::assertSame(Command::SUCCESS, $this->tester($providers)->execute([]));
        self::assertSame(2, $fetcher->calls);
    }

    public function testAnIdentityProviderThatFailsDoesNotStopTheOthers(): void
    {
        $failing = new IdentityProviderSettings('remote', 'https://idp.example.org/metadata', null);
        $noKey = new ServiceProviderSettings(null, null, [], null, false);
        $providers = new IdentityProviders([$failing, Saml::settings()], $noKey, new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());
        $tester = $this->tester($providers);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString(Saml::IDP, $tester->getDisplay());

        $alone = new IdentityProviders([$failing], $noKey, new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());
        self::assertSame(Command::FAILURE, $this->tester($alone)->execute([]));
    }

    public function testACertificateOfTheIdentityProviderThatExpiresSoonFailsTheCheck(): void
    {
        $providers = new IdentityProviders([new IdentityProviderSettings('acme', null, Saml::metadata())], new ServiceProviderSettings(null, null, [], null, false), new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());

        self::assertSame(Command::FAILURE, $this->tester($providers, '2125-12-15 00:00:00 UTC')->execute([]));
    }

    public function testACertificateOfTheServiceProviderThatExpiresSoonFailsTheCheck(): void
    {
        $withoutKey = (string) preg_replace('#<md:KeyDescriptor.*?</md:KeyDescriptor>#', '', Saml::metadata());
        $providers = new IdentityProviders([new IdentityProviderSettings('acme', null, $withoutKey)], new ServiceProviderSettings(null, Saml::pemPair('sp'), [], null, true), new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());
        $tester = $this->tester($providers, '2125-12-15 00:00:00 UTC');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Certificates of the service provider', $tester->getDisplay());
        self::assertStringNotContainsString('Signing certificates of the identity provider', $tester->getDisplay());
    }

    public function testACertificateThatExpiresSoon(): void
    {
        $tester = $this->tester(Saml::providers(new MockClock('2125-12-15 00:00:00 UTC')), '2125-12-15 00:00:00 UTC');

        self::assertSame(Command::FAILURE, $tester->execute(['idp' => 'acme']));
        self::assertStringContainsString('expires soon', $tester->getDisplay());
        self::assertStringContainsString('Sign-ins through an identity provider may fail: see above.', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '10']));
        self::assertSame(Command::FAILURE, $tester->execute(['--days' => '17']));
    }

    public function testAnExpiredCertificateAndMissingMetadata(): void
    {
        $providers = new IdentityProviders(
            [new IdentityProviderSettings('remote', 'https://idp.example.org/metadata', null), Saml::settings()],
            new ServiceProviderSettings(null, null, [['certificate' => Saml::pem('expired.crt'), 'private_key' => Saml::pem('sp.key'), 'passphrase' => null]], null, false),
            new MetadataLoader(new NoFetcher(), new MockClock()),
            new FixedEndpoints(),
        );

        try {
            $providers->encryption();
            self::fail('A mismatched pair was read.');
        } catch (InvalidKey) {
        }

        $tester = $this->tester(Saml::providers(new MockClock('2126-02-01 00:00:00 UTC'), [Saml::settings(), new IdentityProviderSettings('remote', 'https://idp.example.org/metadata', null)]), '2126-02-01 00:00:00 UTC');
        self::assertSame(Command::FAILURE, $tester->execute(['--days' => '0']));
        self::assertStringContainsString('expired', $tester->getDisplay());
        self::assertStringContainsString('The tests fetch nothing', $tester->getDisplay());
        self::assertStringContainsString('remote', $tester->getDisplay());
    }

    public function testNoCertificateOfItsOwn(): void
    {
        $providers = new IdentityProviders([new IdentityProviderSettings('acme', null, Saml::metadata(false))], new ServiceProviderSettings(null, null, [], null, false), new MetadataLoader(new NoFetcher(), new MockClock()), new FixedEndpoints());
        $tester = $this->tester($providers);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringNotContainsString('Certificates of the service provider', $tester->getDisplay());
        self::assertStringContainsString('none', $tester->getDisplay());
    }

    public function testMetadataWithAnExpiry(): void
    {
        $metadata = str_replace('entityID=', 'validUntil="2030-01-01T00:00:00Z" entityID=', Saml::metadata());
        $tester = $this->tester(Saml::providers(new MockClock('2026-11-19 12:00:00 UTC'), [new IdentityProviderSettings('acme', null, $metadata)]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('2030-01-01 00:00 UTC', $tester->getDisplay());
    }

    private function tester(IdentityProviders $providers, string $now = '2026-11-19 12:00:00 UTC'): CommandTester
    {
        $application = new Application();
        $application->addCommand(new CheckCommand($providers, new MockClock($now)));

        return new CommandTester($application->find('phil:saml:check'));
    }
}
