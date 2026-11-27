<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use ArrayObject;
use DateTimeImmutable;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientServices;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\SamlAccountsComposer;
use Phil\IdentityClientBundle\Infrastructure\Saml\ProvisionedSamlAccounts;
use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use Phil\SamlSp\Attribute\SamlIdentity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhilIdentityClientBundle::class)]
#[CoversClass(ClientServices::class)]
#[CoversClass(SamlAccountsComposer::class)]
#[CoversClass(ProvisionedSamlAccounts::class)]
final class SamlAccountsTest extends FunctionalTestCase
{
    public function testTheSamlAccountsGoThroughTheProvisionerOfTheApplication(): void
    {
        $this->boot(['saml' => ['enabled' => true, 'verified_email_from' => ['acme']]]);

        $accounts = $this->samlAccounts();
        self::assertInstanceOf(ProvisionedSamlAccounts::class, $accounts);

        $user = $accounts->create('acme', new SamlIdentity('https://sts.example.test/', 'n-1', null, null, new DateTimeImmutable('@1794657600'), null, null, '_a', [], ['email' => ['ada@example.test'], 'display_name' => ['Ada'], 'groups' => ['staff']]));

        self::assertSame('acme|n-1', $user->getUserIdentifier());
        $identity = $this->provisioner()->last;
        self::assertNotNull($identity);
        self::assertSame('https://sts.example.test/', $identity->issuer);
        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.test'], $identity->claims, 'The claims kept for the provider are kept for SAML too.');
    }

    public function testNoSamlAccountsByDefault(): void
    {
        $this->boot();

        self::assertNull($this->samlAccounts());
    }

    private function samlAccounts(): mixed
    {
        $probe = $this->container()->get('test.saml_accounts');
        self::assertInstanceOf(ArrayObject::class, $probe);

        return $probe[0];
    }
}
