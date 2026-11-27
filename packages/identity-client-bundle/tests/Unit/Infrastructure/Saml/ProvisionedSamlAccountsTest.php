<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure\Saml;

use DateTimeImmutable;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Phil\IdentityClientBundle\Infrastructure\Saml\ProvisionedSamlAccounts;
use Phil\IdentityClientBundle\Tests\Functional\App\RecordingProvisioner;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProvisionedSamlAccounts::class)]
#[CoversClass(VerifiedIdentity::class)]
final class ProvisionedSamlAccountsTest extends TestCase
{
    private const string ENTITY_ID = 'https://sts.example.test/tenant/';

    private const array ALL_CLAIMS = ['email', 'given_name', 'family_name', 'name', 'groups', 'roles', 'acr', 'auth_time', 'sid'];

    public function testTheSubjectCarriesTheIdentityProviderKey(): void
    {
        self::assertSame('acme|5f0c1a52', ProvisionedSamlAccounts::subject('acme', $this->identity()));
        self::assertSame('acme|name-id', ProvisionedSamlAccounts::subject('acme', $this->identity(fields: [])));
    }

    public function testAFirstSignInFindsNoOneAndProvisionsNothing(): void
    {
        $provisioner = new RecordingProvisioner();
        $provisioner->exists = false;

        self::assertNull(new ProvisionedSamlAccounts($provisioner, self::ALL_CLAIMS)->find('acme', $this->identity()));
        self::assertNull($provisioner->last);
    }

    public function testAKnownPersonIsBroughtUpToDate(): void
    {
        $provisioner = new RecordingProvisioner();

        $user = new ProvisionedSamlAccounts($provisioner, self::ALL_CLAIMS)->find('acme', $this->identity());

        self::assertNotNull($user);
        self::assertSame('acme|5f0c1a52', $user->getUserIdentifier());
        self::assertInstanceOf(VerifiedIdentity::class, $provisioner->last);
        self::assertSame('acme|5f0c1a52', $provisioner->last->subject);
    }

    public function testCreateProvisionsTheAccount(): void
    {
        $provisioner = new RecordingProvisioner();
        $provisioner->exists = false;

        $user = new ProvisionedSamlAccounts($provisioner, self::ALL_CLAIMS)->create('acme', $this->identity());

        self::assertSame('acme|5f0c1a52', $user->getUserIdentifier());
        self::assertInstanceOf(VerifiedIdentity::class, $provisioner->last);
        self::assertSame(self::ENTITY_ID, $provisioner->last->issuer);
    }

    public function testTheFieldsBecomeClaimsOfTheOpenIdConnectNames(): void
    {
        $identity = new ProvisionedSamlAccounts(new RecordingProvisioner(), self::ALL_CLAIMS, ['acme'])->identity('acme', $this->identity());

        self::assertSame(self::ENTITY_ID, $identity->issuer);
        self::assertSame('acme|5f0c1a52', $identity->subject);
        self::assertSame([
            'email' => 'ada@example.test',
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
            'name' => 'Ada Lovelace',
            'groups' => ['teachers', 'staff'],
            'roles' => ['Admin'],
            'acr' => 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport',
            'auth_time' => 1794657600,
            'sid' => '_session-1',
        ], $identity->claims);
        self::assertSame('ada@example.test', $identity->verifiedEmail());
    }

    public function testTheAddressOfAnIdentityProviderNotTrustedWithItIsDropped(): void
    {
        $accounts = new ProvisionedSamlAccounts(new RecordingProvisioner(), self::ALL_CLAIMS, ['other']);

        $identity = $accounts->identity('acme', $this->identity());

        self::assertArrayNotHasKey('email', $identity->claims);
        self::assertNull($identity->verifiedEmail());
        self::assertSame('Ada', $identity->claims['given_name']);
    }

    public function testOnlyTheKeptClaimsReachTheProvisioner(): void
    {
        $identity = new ProvisionedSamlAccounts(new RecordingProvisioner(), ['name', 'email_verified'], ['acme'])->identity('acme', $this->identity());

        self::assertSame(['name' => 'Ada Lovelace', 'email_verified' => true], $identity->claims);
    }

    public function testTheVerificationClaimSaysWhetherTheProviderIsTrusted(): void
    {
        $trusted = new ProvisionedSamlAccounts(new RecordingProvisioner(), ['email_verified'], ['acme']);
        $untrusted = new ProvisionedSamlAccounts(new RecordingProvisioner(), ['email_verified']);

        self::assertSame(['email_verified' => true], $trusted->identity('acme', $this->identity())->claims);
        self::assertSame(['email_verified' => false], $untrusted->identity('acme', $this->identity())->claims);
        self::assertSame(['email_verified' => false], $trusted->identity('acmex', $this->identity())->claims);
    }

    public function testEmptyAndMissingFieldsAreLeftOut(): void
    {
        $identity = new ProvisionedSamlAccounts(new RecordingProvisioner(), self::ALL_CLAIMS, ['acme'])->identity('acme', $this->identity(fields: ['display_name' => [''], 'email' => []], context: null, session: null));

        self::assertSame(['auth_time' => 1794657600], $identity->claims);
    }

    /**
     * @param array<value-of<UserField>, list<string>>|null $fields
     */
    private function identity(?array $fields = null, ?string $context = 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport', ?string $session = '_session-1'): SamlIdentity
    {
        return new SamlIdentity(
            self::ENTITY_ID,
            'name-id',
            'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
            $session,
            new DateTimeImmutable('@1794657600'),
            null,
            $context,
            '_assertion-1',
            [],
            $fields ?? [
                'identifier' => ['5f0c1a52'],
                'email' => ['ada@example.test'],
                'given_name' => ['Ada'],
                'family_name' => ['Lovelace'],
                'display_name' => ['Ada Lovelace'],
                'groups' => ['teachers', 'staff'],
                'roles' => ['Admin'],
            ],
        );
    }
}
