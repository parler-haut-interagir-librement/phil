<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSpBundle\Application\Event\SamlAccountCreated;
use Phil\SamlSpBundle\Application\Event\SamlLoginSucceeded;
use Phil\SamlSpBundle\Application\Exception\AccountRefused;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\SamlUser;
use Phil\SamlSpBundle\Application\SamlUsers;
use Phil\SamlSpBundle\Tests\Fixtures\MemoryAccounts;
use Phil\SamlSpBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SamlUsers::class)]
#[CoversClass(SamlUser::class)]
#[CoversClass(AccountRefused::class)]
#[CoversClass(SamlLoginSucceeded::class)]
#[CoversClass(SamlAccountCreated::class)]
final class SamlUsersTest extends TestCase
{
    public function testAUserInTheSession(): void
    {
        $events = new RecordingDispatcher();
        $user = new SamlUsers(Saml::providers(new MockClock()), $events)->user('acme', $this->identity());

        self::assertInstanceOf(SamlUser::class, $user);
        self::assertSame('acme|oid-1', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER', 'ROLE_TEACHER', 'ROLE_ADMIN'], $user->getRoles());
        self::assertSame('acme', $user->identityProvider);
        self::assertSame('ada@example.org', $user->email);
        self::assertSame('Ada Lovelace', $user->displayName);
        self::assertSame([SamlLoginSucceeded::class], $events->classes());
        $event = $events->events[0];
        self::assertInstanceOf(SamlLoginSucceeded::class, $event);
        self::assertSame('acme', $event->identityProvider);
        self::assertSame($user, $event->user);
        self::assertSame('oid-1', $event->identity->subject());
    }

    public function testTheDisplayName(): void
    {
        self::assertSame('Countess', SamlUser::fromIdentity('acme', $this->identity(['display_name' => ['Countess']]), [])->displayName);
        self::assertSame('Ada', SamlUser::fromIdentity('acme', $this->identity(['family_name' => []]), [])->displayName);
        self::assertSame('Lovelace', SamlUser::fromIdentity('acme', $this->identity(['given_name' => []]), [])->displayName);
        self::assertNull(SamlUser::fromIdentity('acme', $this->identity(['given_name' => [], 'family_name' => []]), [])->displayName);
        self::assertNull(SamlUser::fromIdentity('acme', $this->identity(['email' => []]), [])->email);
    }

    public function testAnAccountIsCreatedAtTheFirstSignIn(): void
    {
        $events = new RecordingDispatcher();
        $accounts = new MemoryAccounts();
        $users = new SamlUsers(Saml::providers(new MockClock()), $events, $accounts);

        $first = $users->user('acme', $this->identity());
        self::assertSame('account-0', $first->getUserIdentifier());
        self::assertSame([SamlAccountCreated::class, SamlLoginSucceeded::class], $events->classes());
        $created = $events->events[0];
        self::assertInstanceOf(SamlAccountCreated::class, $created);
        self::assertSame('acme', $created->identityProvider);
        self::assertSame($first, $created->user);

        self::assertSame($first, $users->user('acme', $this->identity()));
        self::assertSame([SamlAccountCreated::class, SamlLoginSucceeded::class, SamlLoginSucceeded::class], $events->classes());
    }

    public function testNoAccountIsCreatedWhenTheIdentityProviderMayNot(): void
    {
        $accounts = new MemoryAccounts();
        $users = new SamlUsers(Saml::providers(new MockClock(), [Saml::settings(createAccounts: false)]), new RecordingDispatcher(), $accounts);

        try {
            $users->user('acme', $this->identity());
            self::fail('An account was created.');
        } catch (AccountRefused $accountRefused) {
            self::assertSame('The person has no account, and the identity provider "acme" may not create one.', $accountRefused->getMessage());
        }

        $accounts->create('acme', $this->identity());
        self::assertSame('account-0', $users->user('acme', $this->identity())->getUserIdentifier());
    }

    public function testTheRolesOfTheConfiguration(): void
    {
        self::assertSame(['ROLE_USER'], SamlUsers::roles(Saml::settings(), $this->identity(['groups' => ['unknown'], 'roles' => []])));
        self::assertSame(['ROLE_USER', 'ROLE_TEACHER'], SamlUsers::roles(Saml::settings(), $this->identity(['groups' => ['0f5b7f6e-1111-4c2d-9e3f-000000000010', '0f5b7f6e-1111-4c2d-9e3f-000000000010'], 'roles' => []])));
    }

    public function testEveryGroupAndRoleIsMappedAndTheRolesAreAList(): void
    {
        $settings = new IdentityProviderSettings('acme', null, Saml::metadata(), groupRoles: ['g1' => 'ROLE_USER', 'g2' => 'ROLE_B', 'r1' => 'ROLE_C'], defaultRoles: ['ROLE_USER']);

        self::assertSame(['ROLE_USER', 'ROLE_B', 'ROLE_C'], SamlUsers::roles($settings, $this->identity(['groups' => ['g1', 'g2'], 'roles' => ['r1']])));
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private function identity(array $fields = []): SamlIdentity
    {
        return new SamlIdentity('https://idp.example.org', 'ada@example.org', null, '_s', new DateTimeImmutable(), null, null, '_a', [], array_replace([
            'identifier' => ['oid-1'],
            'email' => ['ada@example.org'],
            'given_name' => ['Ada'],
            'family_name' => ['Lovelace'],
            'groups' => ['0f5b7f6e-1111-4c2d-9e3f-000000000010'],
            'roles' => ['Admin'],
        ], $fields));
    }
}
