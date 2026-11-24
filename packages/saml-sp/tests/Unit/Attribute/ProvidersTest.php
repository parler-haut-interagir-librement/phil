<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Attribute;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeMap::class)]
#[CoversClass(SamlIdentity::class)]
#[CoversClass(ServiceProvider::class)]
#[CoversClass(IdentityProvider::class)]
#[CoversClass(SecurityPolicy::class)]
#[CoversClass(IdentityProviderRefused::class)]
final class ProvidersTest extends TestCase
{
    public function testTheFirstPresentAttributeWins(): void
    {
        $map = AttributeMap::entraId();
        $values = $map->map([
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => [],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => ['upn@example.org'],
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => ['a', 'b'],
        ]);

        self::assertSame(['email' => ['upn@example.org'], 'groups' => ['a', 'b']], $values);
        self::assertSame([], new AttributeMap()->map(['email' => ['a']]));
    }

    public function testThePresetsCarryTheirExactNames(): void
    {
        $ws = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/';

        self::assertSame([
            'identifier' => ['http://schemas.microsoft.com/identity/claims/objectidentifier'],
            'email' => [$ws.'emailaddress', $ws.'name'],
            'given_name' => [$ws.'givenname'],
            'family_name' => [$ws.'surname'],
            'display_name' => ['http://schemas.microsoft.com/identity/claims/displayname'],
            'groups' => ['http://schemas.microsoft.com/ws/2008/06/identity/claims/groups'],
            'roles' => ['http://schemas.microsoft.com/ws/2008/06/identity/claims/role'],
        ], AttributeMap::entraId()->names);
        self::assertSame([
            'identifier' => ['user_id'],
            'email' => ['email', 'user.email'],
            'given_name' => ['firstName', 'user.firstName'],
            'family_name' => ['lastName', 'user.lastName'],
            'display_name' => ['displayName', 'user.displayName'],
            'groups' => ['groups'],
            'roles' => ['roles'],
        ], AttributeMap::okta()->names);
        self::assertSame([
            'identifier' => ['urn:oid:1.3.6.1.4.1.5923.1.1.1.6', 'eduPersonPrincipalName'],
            'email' => ['mail', 'email', 'urn:oid:0.9.2342.19200300.100.1.3'],
            'given_name' => ['givenName', 'urn:oid:2.5.4.42'],
            'family_name' => ['sn', 'urn:oid:2.5.4.4'],
            'display_name' => ['displayName', 'urn:oid:2.16.840.1.113730.3.1.241'],
            'groups' => ['memberOf', 'groups', 'urn:oid:1.3.6.1.4.1.5923.1.1.1.7'],
            'roles' => ['eduPersonAffiliation', 'urn:oid:1.3.6.1.4.1.5923.1.1.1.1'],
        ], AttributeMap::generic()->names);
    }

    public function testTheFirstOfTwoPresentAttributesIsTheOneRead(): void
    {
        self::assertSame(['email' => ['first@example.org']], AttributeMap::okta()->map(['email' => ['first@example.org'], 'user.email' => ['second@example.org']]));
    }

    public function testTheSubjectIsTheIdentifierBeforeTheNameIdentifier(): void
    {
        $identity = static fn (array $fields): SamlIdentity => new SamlIdentity('https://idp.example.org', 'name-id', null, null, new DateTimeImmutable(), null, null, '_a', [], $fields);

        self::assertSame('oid-1', $identity(['identifier' => ['oid-1']])->subject());
        self::assertSame('name-id', $identity([])->subject());
    }

    public function testAFieldIsReplacedOrUnmapped(): void
    {
        $map = AttributeMap::okta()->with(UserField::Email, ['mail'])->with(UserField::Identifier, []);

        self::assertSame(['mail'], $map->names['email']);
        self::assertSame(['email' => ['x@example.org']], $map->map(['mail' => ['x@example.org'], 'user_id' => ['1']]));
        self::assertSame(['user_id'], AttributeMap::okta()->names['identifier']);
    }

    public function testThePresetsMapEveryField(): void
    {
        $fields = array_map(static fn (UserField $field): string => $field->value, UserField::cases());

        foreach ([AttributeMap::entraId(), AttributeMap::okta(), AttributeMap::generic()] as $map) {
            self::assertSame($fields, array_keys($map->names));
        }

        self::assertSame('http://schemas.microsoft.com/identity/claims/objectidentifier', AttributeMap::entraId()->names['identifier'][0]);
        self::assertSame('http://schemas.microsoft.com/identity/claims/displayname', AttributeMap::entraId()->names['display_name'][0]);
        self::assertSame('http://schemas.microsoft.com/ws/2008/06/identity/claims/role', AttributeMap::entraId()->names['roles'][0]);
        self::assertSame(['mail', 'email', 'urn:oid:0.9.2342.19200300.100.1.3'], AttributeMap::generic()->names['email']);
    }

    public function testAnIdentityReadsItsFields(): void
    {
        $identity = new SamlIdentity('idp', 'name', null, null, new DateTimeImmutable(), null, null, '_a', [], ['groups' => ['a', 'b']]);

        self::assertSame('name', $identity->subject());
        self::assertSame('a', $identity->value(UserField::Groups));
        self::assertSame(['a', 'b'], $identity->values(UserField::Groups));
        self::assertNull($identity->value(UserField::Email));
        self::assertSame([], $identity->values(UserField::Email));
    }

    public function testAServiceProviderIsChecked(): void
    {
        $sp = new ServiceProvider(str_repeat('a', 1024), Url::fromString(Stage::ACS), signRequests: false);
        self::assertNull($sp->signing);
        self::assertSame([], $sp->encryption);
        self::assertNull($sp->nameIdFormat);
        self::assertNull($sp->singleLogoutService);
        self::assertTrue(Stage::sp()->signRequests);

        foreach ([[' ', false, 'The entity ID of the service provider has 1 to 1024 characters.'], [str_repeat('a', 1025), false, 'The entity ID of the service provider has 1 to 1024 characters.'], ['x', true, 'A service provider that signs its requests has a signing key pair.']] as [$entity, $sign, $message]) {
            try {
                new ServiceProvider($entity, Url::fromString(Stage::ACS), signRequests: $sign);
                self::fail('The service provider was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheDefaultsOfAnIdentityProvider(): void
    {
        $idp = new IdentityProvider(Stage::entra()->metadata);

        self::assertTrue($idp->wantAssertionsSigned);
        self::assertFalse($idp->wantAssertionsEncrypted);
        self::assertFalse($idp->allowUnsolicited);
        self::assertSame(Binding::Redirect, $idp->requestBinding);
        self::assertSame([], $idp->attributes->names);
        self::assertSame([Keys::certificate('entra')->pem], array_map(static fn (Certificate $certificate): string => $certificate->pem, $idp->metadata->signingCertificates));
    }

    public function testThePolicyIsBounded(): void
    {
        $policy = new SecurityPolicy();
        self::assertSame(120, $policy->clockSkew);
        self::assertSame(600, $policy->maxAge);
        self::assertSame(600, new SecurityPolicy(600, 30)->clockSkew);
        self::assertSame(86_400, new SecurityPolicy(0, 86_400)->maxAge);

        foreach ([[-1, 600, 'The clock skew is between 0 and 600 seconds; -1 is not.'], [601, 600, 'The clock skew is between 0 and 600 seconds; 601 is not.'], [0, 29, 'The age of a response is between 30 and 86400 seconds; 29 is not.'], [0, 86_401, 'The age of a response is between 30 and 86400 seconds; 86401 is not.']] as [$skew, $age, $message]) {
            try {
                new SecurityPolicy($skew, $age);
                self::fail('The policy was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testARefusalWithoutSecondLevel(): void
    {
        $refused = IdentityProviderRefused::withStatus('urn:x', null);

        self::assertSame('The identity provider answered the status "urn:x".', $refused->getMessage());
        self::assertNull($refused->subStatus);
    }
}
