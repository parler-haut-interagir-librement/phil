<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Application\AccountAddresses;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\Contract\AccountIdGenerator;
use Phil\IdentityProviderBundle\Application\EmailCanonicalizer;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Identifier\UlidAccountIdGenerator;
use Phil\IdentityProviderBundle\Application\Identifier\UuidV7AccountIdGenerator;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Account\AccountState;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountAddressRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The identifiers, the addresses and the states of the accounts: who generates an identifier, what makes two
 * addresses one, which address a purpose uses, and which state signs in.
 */
#[CoversClass(Accounts::class)]
#[CoversClass(AccountAddresses::class)]
#[CoversClass(EmailCanonicalizer::class)]
#[CoversClass(UlidAccountIdGenerator::class)]
#[CoversClass(UuidV7AccountIdGenerator::class)]
#[CoversClass(AccountAddress::class)]
#[CoversClass(AccountState::class)]
#[CoversClass(AddressPurpose::class)]
final class AccountIdentityTest extends TestCase
{
    public function testTheGeneratorsGiveAUlidOrAUuidV7(): void
    {
        $ulid = new UlidAccountIdGenerator()->generate();
        $uuid = new UuidV7AccountIdGenerator()->generate();

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $ulid);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid);
        self::assertNotSame($uuid, new UuidV7AccountIdGenerator()->generate());
        self::assertNotSame($ulid, new UlidAccountIdGenerator()->generate());
        self::assertMatchesRegularExpression(Account::ID_PATTERN, $ulid);
        self::assertMatchesRegularExpression(Account::ID_PATTERN, $uuid);
    }

    public function testRegistrationTakesTheIdentifierOfTheGenerator(): void
    {
        $services = new Services();
        $accounts = new Accounts($services->accounts, $services->hasher, $services->rules, $services->journal, $services->clock, new LockoutPolicy(), null, new class implements AccountIdGenerator {
            #[Override]
            public function generate(): string
            {
                return '0192f5b4-7c1e-7a3b-9f00-123456789abc';
            }
        });

        $account = $accounts->register('ada@example.com', Services::STRONG);
        self::assertNotNull($account);
        self::assertSame('0192f5b4-7c1e-7a3b-9f00-123456789abc', $account->id());
        self::assertSame($account->id(), $services->accounts->findByEmail('ada@example.com')?->id());
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $services->account('bob@example.com')->id());
    }

    public function testTheCanonicalFormOfAnAddress(): void
    {
        $plain = new EmailCanonicalizer();
        $stripping = new EmailCanonicalizer(true);
        $dashes = new EmailCanonicalizer(true, '-');

        self::assertSame('ada+news@example.com', $plain->canonical('ada+news@example.com'));
        self::assertSame('ada@example.com', $stripping->canonical('ada+news@example.com'));
        self::assertSame('ada@example.com', $stripping->canonical('ada+news+more@example.com'));
        self::assertSame('ada@example.com', $stripping->canonical('ada@example.com'));
        self::assertSame('+news@example.com', $stripping->canonical('+news@example.com'));
        self::assertSame('a@example.com', $stripping->canonical('a+@example.com'));
        self::assertSame('ada@ex+ample.com', $stripping->canonical('ada@ex+ample.com'));
        self::assertSame('no-at', $stripping->canonical('no-at'));
        self::assertSame('ada+news@example.com', $dashes->canonical('ada+news@example.com'));
        self::assertSame('ada@example.com', $dashes->canonical('ada-news@example.com'));
    }

    public function testTwoSpellingsOfOneMailboxAreOneAccount(): void
    {
        $services = new Services();
        $accounts = new Accounts($services->accounts, $services->hasher, $services->rules, $services->journal, $services->clock, new LockoutPolicy(), null, new UlidAccountIdGenerator(), new EmailCanonicalizer(true));
        $ada = $accounts->register('Ada+Work@example.com', Services::STRONG);

        self::assertNotNull($ada);
        self::assertSame(['ada+work@example.com', 'ada@example.com'], [$ada->email(), $ada->emailCanonical()]);
        self::assertNull($accounts->register('ada+home@example.com', Services::STRONG));
        self::assertSame([IdentityEventType::RegistrationForKnownEmail->value, $ada->id(), []], $services->audit->last());
        self::assertNull($accounts->register('ada@example.com', Services::STRONG));

        $bob = $accounts->register('bob@example.com', Services::STRONG);
        self::assertNotNull($bob);

        try {
            $accounts->changeEmail($bob, 'ada+other@example.com');
            self::fail('Accepted.');
        } catch (Refused $refused) {
            self::assertSame(['email.taken'], $refused->reasons);
        }

        try {
            $accounts->changeEmail($bob, 'ada+work@example.com');
            self::fail('Accepted.');
        } catch (Refused $refused) {
            self::assertSame(['email.taken'], $refused->reasons);
        }

        $accounts->changeEmail($ada, 'ada+play@example.com');
        self::assertSame(['ada+play@example.com', 'ada@example.com'], [$ada->email(), $ada->emailCanonical()]);
        self::assertSame('ada+play@example.com', $services->accounts->get($ada->id())?->email());
        $accounts->changeEmail($bob, 'robert+x@example.com');
        self::assertSame('robert@example.com', $services->accounts->get($bob->id())?->emailCanonical());
    }

    public function testOnlyAnActiveAccountKeepsItsSessions(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock);
        $accounts = $services->accounts(null, $sessions);
        $account = $services->account();
        $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $open = static fn (): array => self::open($store, $account->id());
        $stored = static fn (): ?Account => $services->accounts->get($account->id());

        self::assertFalse($accounts->changeState($account, AccountState::Active));
        self::assertCount(1, $open());

        $services->clock->modify('+1 hour');
        self::assertTrue($accounts->changeState($account, AccountState::Pending));
        self::assertSame([], $open());
        $pending = $stored();
        self::assertNotNull($pending);
        self::assertSame(AccountState::Pending, $pending->state());
        self::assertSame($services->clock->now()->getTimestamp(), $pending->stateChangedAt()?->getTimestamp());
        self::assertContains([IdentityEventType::AccountStateChanged->value, $account->id(), ['from' => 'active', 'to' => 'pending']], $services->audit->records);
        self::assertTrue($pending->isDisabled());

        $sessions->open($account, 'Firefox on Linux', ['pwd']);
        self::assertTrue($accounts->changeState($account, AccountState::Active));
        self::assertCount(1, $open());
        self::assertSame([IdentityEventType::AccountStateChanged->value, $account->id(), ['from' => 'pending', 'to' => 'active']], $services->audit->last());
        self::assertFalse($stored()?->isDisabled());
        self::assertSame(['active', 'pending', 'suspended', 'closed'], AccountState::values());
        self::assertSame([true, false, false, false], array_map(static fn (AccountState $state): bool => $state->signsIn(), AccountState::cases()));
    }

    /**
     * @return list<AccountSession>
     *
     * @phpstan-impure
     */
    private static function open(InMemoryAccountSessionRepository $store, string $accountId): array
    {
        return $store->openFor($accountId);
    }

    public function testTheAddressesOfAPurpose(): void
    {
        $services = new Services();
        $store = new InMemoryAccountAddressRepository();
        $addresses = new AccountAddresses($store, $services->journal, $services->clock, new EmailCanonicalizer(true));
        $ada = $services->account();
        $bob = $services->account('bob@example.com');

        $purposes = static fn (): array => array_map(static fn (AccountAddress $address): AddressPurpose => $address->purpose(), $addresses->of($ada));
        self::assertSame([], $purposes());
        self::assertNull($addresses->get($ada, AddressPurpose::Recovery));
        self::assertSame('ada@example.com', $addresses->addressFor($ada, AddressPurpose::Recovery));
        self::assertFalse($addresses->verify($ada, AddressPurpose::Recovery));

        $recovery = $addresses->set($ada, AddressPurpose::Recovery, ' Ada+Backup@Example.org ');
        self::assertSame(['ada+backup@example.org', 'ada@example.org', false], [$recovery->address(), $recovery->canonical(), $recovery->isVerified()]);
        self::assertSame([IdentityEventType::AddressSet->value, $ada->id(), ['purpose' => 'recovery']], $services->audit->last());
        self::assertSame('ada@example.com', $addresses->addressFor($ada, AddressPurpose::Recovery));
        self::assertSame($services->clock->now()->getTimestamp(), $recovery->createdAt()->getTimestamp());

        self::assertTrue($addresses->verify($ada, AddressPurpose::Recovery));
        self::assertSame([IdentityEventType::AddressVerified->value, $ada->id(), ['purpose' => 'recovery']], $services->audit->last());
        $count = \count($services->audit->records);
        self::assertTrue($addresses->verify($ada, AddressPurpose::Recovery));
        self::assertCount($count, $services->audit->records);
        self::assertSame('ada+backup@example.org', $addresses->addressFor($ada, AddressPurpose::Recovery));
        self::assertSame('ada@example.com', $addresses->addressFor($ada, AddressPurpose::Notices));
        self::assertSame('ada@example.com', $addresses->addressFor($ada, AddressPurpose::SignIn));

        $same = $addresses->set($ada, AddressPurpose::Recovery, 'ada+other@example.org');
        self::assertSame($recovery->id(), $same->id());
        self::assertFalse($same->isVerified());
        self::assertSame('ada@example.com', $addresses->addressFor($ada, AddressPurpose::Recovery));
        $addresses->set($ada, AddressPurpose::Notices, 'news@example.net');
        self::assertSame([AddressPurpose::Notices, AddressPurpose::Recovery], $purposes());

        foreach ([
            [AddressPurpose::Recovery, 'ada+again@example.org', 'email.taken'],
            [AddressPurpose::Recovery, 'not an address', 'email.invalid'],
            [AddressPurpose::SignIn, 'bob2@example.com', 'address.sign_in'],
        ] as [$purpose, $address, $reason]) {
            try {
                $addresses->set($bob, $purpose, $address);
                self::fail('Accepted.');
            } catch (Refused $refused) {
                self::assertSame([$reason], $refused->reasons);
            }
        }

        $addresses->set($bob, AddressPurpose::Notices, 'ada+x@example.org');
        self::assertSame($bob->id(), $store->findByCanonical(AddressPurpose::Notices, 'ada@example.org')?->accountId());

        $addresses->remove($ada, AddressPurpose::Recovery);
        self::assertSame([IdentityEventType::AddressRemoved->value, $ada->id(), ['purpose' => 'recovery']], $services->audit->last());
        self::assertNull($addresses->get($ada, AddressPurpose::Recovery));
        $count = \count($services->audit->records);
        $addresses->remove($ada, AddressPurpose::Recovery);
        self::assertCount($count, $services->audit->records);
        self::assertSame(['sign_in', 'recovery', 'notices'], AddressPurpose::values());
    }

    public function testAnAddressRowRefusesTheSignInPurposeAndKeepsItsKey(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');

        try {
            AccountAddress::add('A', AddressPurpose::SignIn, 'a@example.com', 'a@example.com', $now);
            self::fail('Accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString('sign-in', $invalidValue->getMessage());
        }

        $address = AccountAddress::add('A', AddressPurpose::Recovery, 'A@Example.com', 'a@example.com', $now);
        self::assertSame(hash('sha256', 'recovery A'), $address->id());
        self::assertSame(AccountAddress::idOf('A', AddressPurpose::Recovery), $address->id());
        self::assertNotSame(AccountAddress::idOf('A', AddressPurpose::Notices), $address->id());
        self::assertSame(['A', 'a@example.com', null], [$address->accountId(), $address->address(), $address->verifiedAt()]);
        $address->verify($now);
        $address->verify($now->modify('+1 day'));
        self::assertSame($now, $address->verifiedAt());
        $address->change('a@example.com', 'a@example.com');
        self::assertSame($now, $address->verifiedAt());
        $address->change('b@example.com', 'b');
        self::assertSame(['b@example.com', 'b', null], [$address->address(), $address->canonical(), $address->verifiedAt()]);
    }
}
